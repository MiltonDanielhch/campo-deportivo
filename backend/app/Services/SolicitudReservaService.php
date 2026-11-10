<?php

namespace App\Services;

use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCreadaDTO;
use App\DTOs\SolicitudFiltrosDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Jobs\ExpirarSolicitudJob;
use App\Jobs\PollingSolicitudJob;
use App\Jobs\ReintentarSolicitudJob;
use App\Models\Asociacion;
use App\Models\Funcionario;
use App\Models\ParametroSistema;
use App\Models\SolicitudDetalleAlumno;
use App\Models\SolicitudReserva;
use App\Services\Sireb\SirebLiquidacionService;
use App\Services\SolicitudesReserva\SolicitudReservaAdminQueryService;
use App\Services\SolicitudesReserva\SolicitudReservaPricingService;
use App\Services\SolicitudesReserva\SolicitudReservaValidationService;
use Generator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador de creación de solicitudes de reserva multi-franja (HU-D1, HU-D2, HU-D3, HU-D8).
 *
 * Después de los refactors #57/#58/#59, este service solo orquesta:
 *   - crear() / regularizar(): flujo principal
 *   - crearLiquidacionEnSireb(): construcción de items + delegación al helper central
 *   - construirRespuesta(): shape de la respuesta al frontend
 *   - delegadores de admin query (#57)
 *
 * La validación de franjas, anti-hoarding, convenio/alumnos y helpers de
 * expiración/código/nombre viven en SolicitudReservaValidationService (#59).
 * La resolución de tarifa/variante/servicio vive en SolicitudReservaPricingService (#58).
 * El listado/stats/export/detalle admin viven en SolicitudReservaAdminQueryService (#57).
 *
 * Dos capas de protección anti-doble-reserva:
 *   1. Verificación rápida (aplicativa) con la grilla del Módulo 3.
 *   2. Escritura protegida: la restricción EXCLUDE decide en concurrencia.
 *
 * Recién DESPUÉS de que la transacción tiene éxito se llama a SIREB.
 *
 * Canal público (HU-D8): si SIREB no responde, la solicitud queda 'pendiente'
 * y se despacha un ReintentarSolicitudJob (5 reintentos × 60s). Solo si los
 * 5 fallan, la solicitud pasa a 'rechazada' con motivo 'error_cobro_inicial'
 * y la franja se libera.
 *
 * Canal ventanilla (esAdmin): si SIREB falla, NO hay reintento silencioso.
 * La solicitud se rechaza inline (motivo 'cobro_ventanilla_fallo') y se
 * propaga la excepción para que el controller devuelva 422 con el mensaje
 * real. El funcionario frente al ciudadano necesita el código YA o el error,
 * no una cola fantasma. La franja se libera sola al salir de estado activo
 * (el EXCLUDE deja de contarla), sin transacción de decremento.
 *
 * La persistencia de la liquidación vive en SirebLiquidacionService (helper
 * central compartido con piscina). Este servicio solo construye los items
 * (dominio de canchas: resuelve tarifa por campo/hora con CatalogoSirebService)
 * y DECIDE la política ante fallo según el canal.
 *
 * El monto de la solicitud se calcula localmente como ESTIMACIÓN (según
 * tipo de tarifa diurna/nocturna de la franja). Al crear la liquidación,
 * SIREB es la fuente de verdad: monto_total se sobrescribe con el monto
 * devuelto por el gateway.
 */
class SolicitudReservaService
{
    public function __construct(
        private AuditoriaService $auditoria,
        private CatalogoSirebService $catalogo,
        private SirebLiquidacionService $liquidaciones,
        private SolicitudReservaAdminQueryService $adminQuery,
        private SolicitudReservaPricingService $pricing,
        private SolicitudReservaValidationService $validation,
    ) {}

    /**
     * [Fase 6a] 3er arg opcional: asociación vigente del token del ciudadano.
     * Si no-null, el service BYPASSEA resolverAsociacionVigente(payload CI) y
     * usa esta asociación directamente → abre visibility gate de convenio,
     * da plazo largo, y valida alumnos contra su id.
     * Si null (anónimo o ciudadano no-asociado), comportamiento idéntico a hoy.
     */
    public function crear(
        SolicitudReservaDTO $datos,
        bool $esAdmin = false,
        ?Asociacion $convenioAsociacion = null,
    ): SolicitudCreadaDTO {
        // ── Gate anti-hoarding: SOLO canal público ──
        if (! $esAdmin) {
            $this->validation->validarAntiHoarding($datos, $convenioAsociacion);
        }

        // [convenio] Si el pagador es responsable de una asociación VIGENTE,
        // la solicitud es de convenio: plazo largo + tope por CI propio.
        $asociacion = $convenioAsociacion ?? $this->validation->resolverAsociacionVigente($datos->ciNitPagador);

        // [Fase 6a] Flag que abre el visibility gate de variantes de convenio.
        $puedeUsarNoVisible = $convenioAsociacion !== null;

        // ── Capa 1: verificación rápida (aplicativa) ──
        $campos = $this->validation->verificarFranjas($datos->franjas);

        // ── P8.2B: resolver variante/tarifa/cantidad ANTES de persistir ──
        $franjasResueltas = $this->pricing->resolverFranjasParaCrear($datos->franjas, $campos, $esAdmin, $puedeUsarNoVisible, $convenioAsociacion);

        // [convenio-guard] variante de convenio solo pa' asociación VIGENTE del mismo tipo.
        $this->validation->validarVariantesConvenio($franjasResueltas, $asociacion);

        // [Fase 3] alumnos asignados deben pertenecer a la asociacion del pagador.
        $this->validation->validarAlumnosPertenencia($franjasResueltas, $asociacion);

        // ── P8.4B: hora de inicio más temprana por campo ──
        $horasPorCampo = $this->pricing->calcularHorasPorCampo($franjasResueltas);

        // ── P8.3B / P8.4B: resolver servicios adicionales ANTES de persistir ──
        $serviciosResueltos = $this->pricing->resolverServiciosParaCrear(
            $datos->servicios,
            $campos,
            $esAdmin,
            $horasPorCampo,
            $puedeUsarNoVisible,
        );

        $montoTotal = round(
            collect($franjasResueltas)->sum('subtotal')
            + collect($serviciosResueltos)->sum('subtotal'),
            2
        );

        // ── Capa 2: escritura protegida (EXCLUDE) ──
        $expiracionMinutos = $this->validation->calcularExpiracionMinutos($asociacion, $esAdmin, $convenioAsociacion !== null);

        try {
            $solicitud = DB::transaction(function () use ($datos, $franjasResueltas, $serviciosResueltos, $montoTotal, $expiracionMinutos) {
                $solicitudNueva = SolicitudReserva::create([
                    'codigo_seguimiento' => $this->validation->generarCodigoSeguimiento(),
                    'monto_total' => $montoTotal,
                    'nombre_pagador' => $this->validation->normalizarPagador($datos->nombrePagador),
                    'telefono_pagador' => $datos->telefonoPagador,
                    'ci_nit_pagador' => $datos->ciNitPagador,
                    'ip_cobrador' => $datos->ipCobrador,
                    'estado' => EstadoSolicitudReserva::Pendiente,
                    'expira_en' => now()->addMinutes($expiracionMinutos),
                ]);

                foreach ($franjasResueltas as $resuelta) {
                    /** @var \App\DTOs\FranjaSolicitadaDTO $franja */
                    $franja = $resuelta['franja'];

                    $detalle = $solicitudNueva->detalles()->create([
                        'campo_id' => $franja->campoId,
                        'variante_id' => $resuelta['variante_id'],
                        'servicio_sireb_id' => $resuelta['servicio_sireb_id'],
                        'tarifa_sireb_id' => $resuelta['tarifa_sireb_id'],
                        'etiqueta_sireb' => $resuelta['etiqueta_sireb'],
                        'cantidad' => $resuelta['cantidad'],
                        'fecha_reserva' => $franja->fecha,
                        'hora_inicio' => $franja->horaInicio,
                        'hora_fin' => $franja->horaFin,
                        'tarifa_aplicada' => $resuelta['precio_unitario'],
                    ]);

                    // [Fase 3] puente alumno<->franja.
                    foreach (array_unique($franja->alumnosIds) as $alumnoId) {
                        SolicitudDetalleAlumno::create([
                            'detalle_id' => $detalle->id,
                            'alumno_id' => $alumnoId,
                        ]);
                    }
                }

                foreach ($serviciosResueltos as $resuelto) {
                    /** @var \App\DTOs\ServicioAdicionalDTO $servicio */
                    $servicio = $resuelto['servicio'];

                    $solicitudNueva->servicios()->create([
                        'campo_id' => $resuelto['campo_id'],
                        'variante_id' => $resuelto['variante_id'],
                        'servicio_sireb_id' => $resuelto['servicio_sireb_id'],
                        'tarifa_sireb_id' => $resuelto['tarifa_sireb_id'],
                        'etiqueta_sireb' => $resuelto['etiqueta_sireb'],
                        'cantidad' => $resuelto['cantidad'],
                        'precio_unitario' => $resuelto['precio_unitario'],
                        'subtotal' => $resuelto['subtotal'],
                    ]);
                }

                return $solicitudNueva;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                try {
                    $this->validation->verificarFranjas($datos->franjas);
                } catch (FranjaNoDisponibleException $especifica) {
                    throw $especifica;
                }

                throw new FranjaNoDisponibleException(
                    'Una de las franjas seleccionadas ya no está disponible.',
                );
            }
            throw $e;
        }

        try {
            $this->crearLiquidacionEnSireb($solicitud);
        } catch (\Throwable $e) {
            // Ventanilla: sin reintento silencioso.
            if ($esAdmin) {
                $solicitud->update([
                    'estado' => EstadoSolicitudReserva::Rechazada,
                    'motivo_rechazo' => 'cobro_ventanilla_fallo',
                ]);

                Log::channel('sireb')->warning(
                    'Liquidación de ventanilla falló, solicitud rechazada',
                    [
                        'solicitud_id' => $solicitud->id,
                        'error' => $e->getMessage(),
                    ]
                );

                throw $e;
            }

            Log::channel('sireb')->warning(
                'Creación de liquidación falló, despachando reintento',
                [
                    'solicitud_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ]
            );

            ReintentarSolicitudJob::dispatch($solicitud->id);
        }

        ExpirarSolicitudJob::dispatch($solicitud->id)->delay($solicitud->expira_en);

        $intervaloPolling = (int) ParametroSistema::where(
            'clave',
            'polling_intervalo_segundos'
        )->value('valor');
        PollingSolicitudJob::dispatch($solicitud->id)
            ->delay(now()->addSeconds($intervaloPolling ?: 20));

        return $this->construirRespuesta($solicitud);
    }

    /**
     * [B77-B] Regularización de uso tras caída del sistema (admin-only).
     *
     * Caso: el ciudadano usó la cancha pero no pudo pagar (SIREB down / sin saldo
     * en el momento). El admin registra el uso y genera el código de pago AHORA.
     *
     * Diferencia con crear(): salta validarVentana + validarFranjaFutura y tolera
     * bloques 'pasada' en la grilla. TODO lo demás (disponibilidad, EXCLUDE,
     * precio espejo por franja, liquidación SIREB con política admin, jobs) es
     * IDÉNTICO a la venta: la integridad NO se relaja, solo el gate de tiempo de
     * VENTA (que no aplica a un uso ya ocurrido).
     *
     * El motivo queda en auditoría (regularizacion_uso_post_caida). NO se agrega
     * columna 'regularizada' (sin migration); el detalle puede inferirlo de la
     * auditoría.
     *
     * @throws FranjaNoDisponibleException|ValidationException|\Throwable
     */
    public function regularizar(SolicitudReservaDTO $datos, string $motivo, Funcionario $funcionario): SolicitudCreadaDTO
    {
        // Capa 1 con gates de tiempo saltados (regularización).
        $campos = $this->validation->verificarFranjas($datos->franjas, saltarGatesTiempo: true);

        $franjasResueltas = $this->pricing->resolverFranjasParaCrear($datos->franjas, $campos, esAdmin: true);

        $horasPorCampo = $this->pricing->calcularHorasPorCampo($franjasResueltas);

        $serviciosResueltos = $this->pricing->resolverServiciosParaCrear(
            $datos->servicios,
            $campos,
            true,
            $horasPorCampo,
        );

        $montoTotal = round(
            collect($franjasResueltas)->sum('subtotal')
            + collect($serviciosResueltos)->sum('subtotal'),
            2
        );

        // [convenio] regularización también puede ser de asociación vigente.
        $asociacion = $this->validation->resolverAsociacionVigente($datos->ciNitPagador);
        // [convenio-fix #4] regularizar es admin-only (ventanilla) -> plazo largo si es asociacion.
        $expiracionMinutos = $this->validation->calcularExpiracionMinutos($asociacion, true);

        try {
            $solicitud = DB::transaction(function () use ($datos, $franjasResueltas, $serviciosResueltos, $montoTotal, $expiracionMinutos) {
                $solicitudNueva = SolicitudReserva::create([
                    'codigo_seguimiento' => $this->validation->generarCodigoSeguimiento(),
                    'monto_total' => $montoTotal,
                    'nombre_pagador' => $this->validation->normalizarPagador($datos->nombrePagador),
                    'telefono_pagador' => $datos->telefonoPagador,
                    'ci_nit_pagador' => $datos->ciNitPagador,
                    'ip_cobrador' => $datos->ipCobrador,
                    'estado' => EstadoSolicitudReserva::Pendiente,
                    'expira_en' => now()->addMinutes($expiracionMinutos),
                ]);

                foreach ($franjasResueltas as $resuelta) {
                    /** @var \App\DTOs\FranjaSolicitadaDTO $franja */
                    $franja = $resuelta['franja'];

                    $solicitudNueva->detalles()->create([
                        'campo_id' => $franja->campoId,
                        'variante_id' => $resuelta['variante_id'],
                        'servicio_sireb_id' => $resuelta['servicio_sireb_id'],
                        'tarifa_sireb_id' => $resuelta['tarifa_sireb_id'],
                        'etiqueta_sireb' => $resuelta['etiqueta_sireb'],
                        'cantidad' => $resuelta['cantidad'],
                        'fecha_reserva' => $franja->fecha,
                        'hora_inicio' => $franja->horaInicio,
                        'hora_fin' => $franja->horaFin,
                        'tarifa_aplicada' => $resuelta['precio_unitario'],
                    ]);
                }

                foreach ($serviciosResueltos as $resuelto) {
                    /** @var \App\DTOs\ServicioAdicionalDTO $servicio */
                    $servicio = $resuelto['servicio'];

                    $solicitudNueva->servicios()->create([
                        'campo_id' => $resuelto['campo_id'],
                        'variante_id' => $resuelto['variante_id'],
                        'servicio_sireb_id' => $resuelto['servicio_sireb_id'],
                        'tarifa_sireb_id' => $resuelto['tarifa_sireb_id'],
                        'etiqueta_sireb' => $resuelto['etiqueta_sireb'],
                        'cantidad' => $resuelto['cantidad'],
                        'precio_unitario' => $resuelto['precio_unitario'],
                        'subtotal' => $resuelto['subtotal'],
                    ]);
                }

                return $solicitudNueva;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                try {
                    $this->validation->verificarFranjas($datos->franjas, saltarGatesTiempo: true);
                } catch (FranjaNoDisponibleException $especifica) {
                    throw $especifica;
                }

                throw new FranjaNoDisponibleException(
                    'Una de las franjas seleccionadas ya no está disponible.',
                );
            }
            throw $e;
        }

        // [B77-B] el motivo queda trazable SIEMPRE que la regularización se
        // materializó como solicitud (antes de intentar el cobro).
        $this->auditoria->registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'regularizacion_uso_post_caida',
            $funcionario->id,
            null,
            [
                'motivo' => $motivo,
                'canal' => 'panel_admin_regularizacion',
                'franjas' => count($datos->franjas),
                'monto_total' => $montoTotal,
            ],
        );

        try {
            $this->crearLiquidacionEnSireb($solicitud);
        } catch (\Throwable $e) {
            // Política admin (ventanilla): sin reintento silencioso.
            $solicitud->update([
                'estado' => EstadoSolicitudReserva::Rechazada,
                'motivo_rechazo' => 'cobro_regularizacion_fallo',
            ]);

            Log::channel('sireb')->warning(
                'Liquidación de regularización falló, solicitud rechazada',
                [
                    'solicitud_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }

        ExpirarSolicitudJob::dispatch($solicitud->id)->delay($solicitud->expira_en);

        $intervaloPolling = (int) ParametroSistema::where(
            'clave',
            'polling_intervalo_segundos'
        )->value('valor');
        PollingSolicitudJob::dispatch($solicitud->id)
            ->delay(now()->addSeconds($intervaloPolling ?: 20));

        return $this->construirRespuesta($solicitud);
    }

    /**
     * Construye los items de canchas y delega la liquidación al helper central.
     *
     * La resolución de tarifa por campo/hora (CatalogoSirebService) sigue
     * siendo dominio de canchas y vive acá; lo que el helper concentra es el
     * contrato con SIREB + el shape de datos_cobro_pendiente + auditoría.
     *
     * NO atrapa excepciones: la política (reintento público / rechazo
     * ventanilla) vive en crear().
     *
     * @throws \Throwable si algo falla (lo captura el caller para decidir)
     */
    private function crearLiquidacionEnSireb(SolicitudReserva $solicitud): void
    {
        $items = [];

        foreach ($solicitud->detalles as $detalle) {
            // P8.2B: usar snapshot persistido en la línea.
            $tarifaId = $detalle->tarifa_sireb_id;

            // Fallback para solicitudes antiguas creadas antes de P8.2B.
            if (blank($tarifaId)) {
                $campo = $detalle->campo;
                $tarifaId = $this->catalogo->resolverTarifaId($campo, $detalle->hora_inicio);
            }

            $items[] = [
                'tarifa_id' => $tarifaId,
                'cantidad' => (int) ($detalle->cantidad ?? 1),
            ];
        }

        foreach ($solicitud->servicios as $servicio) {
            if (blank($servicio->tarifa_sireb_id)) {
                throw new \RuntimeException("Servicio adicional sin tarifa SIREB espejada: {$servicio->id}");
            }

            $items[] = [
                'tarifa_id' => $servicio->tarifa_sireb_id,
                'cantidad' => (int) ($servicio->cantidad ?? 1),
            ];
        }

        $this->liquidaciones->crearYPersistir(
            reserva: $solicitud,
            items: $items,
            idempotencyPrefix: 'sedede:reserva',
            tablaAuditoria: 'solicitudes_reserva',
        );
    }

    /**
     * Construye la respuesta al frontend a partir de la solicitud.
     */
    private function construirRespuesta(SolicitudReserva $solicitud): SolicitudCreadaDTO
    {
        $datosCobro = $solicitud->datos_cobro_pendiente ?? [];

        $respuestaCore = new RespuestaCobroDTO(
            referenciaRecaudaciones: $datosCobro['codigo_publico'] ?? $solicitud->codigo_seguimiento,
            qrString: $datosCobro['qr_string'] ?? null,
            qrImageBase64: null,
            checkoutUrl: null,
        );

        return new SolicitudCreadaDTO($solicitud, $respuestaCore);
    }

    // ─── Delegadores admin query (PR #57) ────────────────────────────────

    public function esAdministrador(Funcionario $funcionario): bool
    {
        return $this->adminQuery->esAdministrador($funcionario);
    }

    public function listarAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): LengthAwarePaginator
    {
        return $this->adminQuery->listarAdmin($filtros, $funcionario);
    }

    public function calcularStatsAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): array
    {
        return $this->adminQuery->calcularStatsAdmin($filtros, $funcionario);
    }

    /**
     * @return Generator<int, array<int, string>>
     */
    public function exportarCsv(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): Generator
    {
        return $this->adminQuery->exportarCsv($filtros, $funcionario);
    }

    /**
     * @return array{solicitud: SolicitudReserva, auditoria: \Illuminate\Support\Collection<int, \App\Models\Auditoria>}
     */
    public function obtenerAdmin(string $id, Funcionario $funcionario): array
    {
        return $this->adminQuery->obtenerAdmin($id, $funcionario);
    }
}
