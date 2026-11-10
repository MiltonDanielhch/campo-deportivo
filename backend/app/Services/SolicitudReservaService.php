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
 * Orquestador de solicitudes de reserva multi-franja (HU-D1, HU-D2, HU-D3, HU-D8).
 *
 * Después de #57/#58/#59/#60, este service SOLO orquesta:
 *   - crear() / regularizar(): flujo principal
 *   - persistirSolicitud(): transacción DB compartida
 *   - manejarConflictoExclude(): recuperación del 23P01
 *   - crearLiquidacionConPolitica(): política de cobro por canal
 *   - despacharJobs(): Expirar + Polling
 *   - crearLiquidacionEnSireb(): items + delegación al helper central
 *   - construirRespuesta(): shape al frontend
 *   - delegadores de admin query (#57)
 *
 * Responsabilidades externas:
 *   - SolicitudReservaValidationService (#59): franjas, anti-hoarding, convenio,
 *     alumnos, expiración, código, normalización de nombre.
 *   - SolicitudReservaPricingService (#58): tarifa/variante/servicio.
 *   - SolicitudReservaAdminQueryService (#57): listado/stats/export/detalle.
 *
 * Dos capas anti-doble-reserva:
 *   1. Verificación rápida (aplicativa) con la grilla del Módulo 3.
 *   2. Escritura protegida: la restricción EXCLUDE decide en concurrencia.
 *
 * Recién DESPUÉS de que la transacción tiene éxito se llama a SIREB.
 *
 * Canal público (HU-D8): si SIREB no responde, la solicitud queda 'pendiente'
 * y se despacha ReintentarSolicitudJob (5 reintentos × 60s). Solo si los 5
 * fallan, pasa a 'rechazada' con motivo 'error_cobro_inicial' y la franja se
 * libera.
 *
 * Canal ventanilla (esAdmin): si SIREB falla, NO hay reintento silencioso.
 * La solicitud se rechaza inline (motivo 'cobro_ventanilla_fallo' o
 * 'cobro_regularizacion_fallo') y se propaga la excepción para que el
 * controller devuelva 422 con el mensaje real. El funcionario frente al
 * ciudadano necesita el código YA o el error, no una cola fantasma. La franja
 * se libera sola al salir de estado activo (el EXCLUDE deja de contarla), sin
 * transacción de decremento.
 *
 * La persistencia de la liquidación vive en SirebLiquidacionService (helper
 * central compartido con piscina). Este service solo construye los items
 * (dominio de canchas: resuelve tarifa por campo/hora con CatalogoSirebService)
 * y DECIDE la política ante fallo según el canal.
 *
 * El monto se calcula localmente como ESTIMACIÓN (según tipo de tarifa
 * diurna/nocturna de la franja). Al crear la liquidación, SIREB es la fuente
 * de verdad: monto_total se sobrescribe con el monto devuelto por el gateway.
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
     * Si no-null, BYPASSEA resolverAsociacionVigente(payload CI) y usa esta
     * asociación directamente → abre visibility gate de convenio, da plazo
     * largo, y valida alumnos contra su id.
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

        // [convenio] asociación VIGENTE del pagador (token o CI del payload).
        $asociacion = $convenioAsociacion
            ?? $this->validation->resolverAsociacionVigente($datos->ciNitPagador);

        // [Fase 6a] abre visibility gate de variantes de convenio sin flipo flag.
        $puedeUsarNoVisible = $convenioAsociacion !== null;

        // ── Capa 1: verificación rápida (aplicativa) ──
        $campos = $this->validation->verificarFranjas($datos->franjas);

        // ── P8.2B: resolver variante/tarifa/cantidad ANTES de persistir ──
        $franjasResueltas = $this->pricing->resolverFranjasParaCrear(
            $datos->franjas,
            $campos,
            $esAdmin,
            $puedeUsarNoVisible,
            $convenioAsociacion,
        );

        // [convenio-guard] variante de convenio solo pa' asociación VIGENTE del mismo tipo.
        $this->validation->validarVariantesConvenio($franjasResueltas, $asociacion);

        // [Fase 3] alumnos asignados deben pertenecer a la asociación del pagador.
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
        $expiracionMinutos = $this->validation->calcularExpiracionMinutos(
            $asociacion,
            $esAdmin,
            $convenioAsociacion !== null,
        );

        try {
            $solicitud = $this->persistirSolicitud(
                $datos,
                $franjasResueltas,
                $serviciosResueltos,
                $montoTotal,
                $expiracionMinutos,
                incluirAlumnos: true,
            );
        } catch (QueryException $e) {
            $this->manejarConflictoExclude($e, $datos->franjas, saltarGatesTiempo: false);
        }

        $this->crearLiquidacionConPolitica(
            $solicitud,
            $esAdmin,
            motivoRechazo: 'cobro_ventanilla_fallo',
            logMensaje: 'Liquidación de ventanilla falló, solicitud rechazada',
        );

        $this->despacharJobs($solicitud);

        return $this->construirRespuesta($solicitud);
    }

    /**
     * [B77-B] Regularización de uso tras caída del sistema (admin-only).
     *
     * Caso: el ciudadano usó la cancha pero no pudo pagar (SIREB down / sin
     * saldo en el momento). El admin registra el uso y genera el código de
     * pago AHORA.
     *
     * Diferencia con crear(): salta validarVentana + validarFranjaFutura y
     * tolera bloques 'pasada' en la grilla. TODO lo demás (disponibilidad,
     * EXCLUDE, precio espejo por franja, liquidación SIREB con política admin,
     * jobs) es IDÉNTICO a la venta: la integridad NO se relaja, solo el gate
     * de tiempo de VENTA (que no aplica a un uso ya ocurrido).
     *
     * El motivo queda en auditoría (regularizacion_uso_post_caida). NO se
     * agrega columna 'regularizada' (sin migration); el detalle puede
     * inferirlo de la auditoría.
     *
     * @throws FranjaNoDisponibleException|\Throwable
     */
    public function regularizar(
        SolicitudReservaDTO $datos,
        string $motivo,
        Funcionario $funcionario,
    ): SolicitudCreadaDTO {
        // Capa 1 con gates de tiempo saltados (regularización).
        $campos = $this->validation->verificarFranjas($datos->franjas, saltarGatesTiempo: true);

        $franjasResueltas = $this->pricing->resolverFranjasParaCrear(
            $datos->franjas,
            $campos,
            esAdmin: true,
        );

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
        // [convenio-fix #4] regularizar es admin-only (ventanilla) -> plazo largo si es asociación.
        $expiracionMinutos = $this->validation->calcularExpiracionMinutos($asociacion, true);

        try {
            $solicitud = $this->persistirSolicitud(
                $datos,
                $franjasResueltas,
                $serviciosResueltos,
                $montoTotal,
                $expiracionMinutos,
                incluirAlumnos: false,
            );
        } catch (QueryException $e) {
            $this->manejarConflictoExclude($e, $datos->franjas, saltarGatesTiempo: true);
        }

        // [B77-B] el motivo queda trazable SIEMPRE que la regularización se
        // materializó como solicitud (antes de intentar el cobro, para que un
        // fallo de SIREB no borre el registro del acto administrativo).
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

        // Política admin (ventanilla): sin reintento silencioso. Siempre rechaza inline.
        $this->crearLiquidacionConPolitica(
            $solicitud,
            esAdmin: true,
            motivoRechazo: 'cobro_regularizacion_fallo',
            logMensaje: 'Liquidación de regularización falló, solicitud rechazada',
        );

        $this->despacharJobs($solicitud);

        return $this->construirRespuesta($solicitud);
    }

    // ─── Helpers de orquestación (PR #60) ────────────────────────────────

    /**
     * Transacción DB compartida por crear() y regularizar().
     *
     * $incluirAlumnos: true en crear() (escribe el puente alumno↔franja);
     * false en regularizar() (el DTO de regularización trae alumnosIds=[]).
     * array_unique evita que un payload con el mismo alumno dos veces viole
     * el unique (detalle_id, alumno_id).
     */
    private function persistirSolicitud(
        SolicitudReservaDTO $datos,
        array $franjasResueltas,
        array $serviciosResueltos,
        float $montoTotal,
        int $expiracionMinutos,
        bool $incluirAlumnos,
    ): SolicitudReserva {
        return DB::transaction(function () use (
            $datos,
            $franjasResueltas,
            $serviciosResueltos,
            $montoTotal,
            $expiracionMinutos,
            $incluirAlumnos
        ) {
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

                if ($incluirAlumnos) {
                    foreach (array_unique($franja->alumnosIds) as $alumnoId) {
                        SolicitudDetalleAlumno::create([
                            'detalle_id' => $detalle->id,
                            'alumno_id' => $alumnoId,
                        ]);
                    }
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
    }

    /**
     * Recuperación del 23P01 (EXCLUDE) en concurrencia.
     *
     * Si el código no es 23P01, re-lanza tal cual. Si lo es, re-verifica las
     * franjas para devolver el mensaje específico de la franja en conflicto;
     * si la re-verificación no identifica cuál, lanza el genérico.
     */
    private function manejarConflictoExclude(
        QueryException $e,
        array $franjas,
        bool $saltarGatesTiempo,
    ): void {
        if ($e->getCode() !== '23P01') {
            throw $e;
        }

        try {
            $this->validation->verificarFranjas($franjas, $saltarGatesTiempo);
        } catch (FranjaNoDisponibleException $especifica) {
            throw $especifica;
        }

        throw new FranjaNoDisponibleException(
            'Una de las franjas seleccionadas ya no está disponible.',
        );
    }

    /**
     * Cobro SIREB con política por canal.
     *
     * esAdmin=true: rechaza inline con $motivoRechazo, loguea $logMensaje y
     * propaga la excepción (controller devuelve 422).
     * esAdmin=false: loguea y despacha ReintentarSolicitudJob (no propaga).
     *
     * NO atrapa la excepción en el branch admin: el caller la necesita.
     */
    private function crearLiquidacionConPolitica(
        SolicitudReserva $solicitud,
        bool $esAdmin,
        string $motivoRechazo,
        string $logMensaje,
    ): void {
        try {
            $this->crearLiquidacionEnSireb($solicitud);
        } catch (\Throwable $e) {
            if ($esAdmin) {
                $solicitud->update([
                    'estado' => EstadoSolicitudReserva::Rechazada,
                    'motivo_rechazo' => $motivoRechazo,
                ]);

                Log::channel('sireb')->warning($logMensaje, [
                    'solicitud_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ]);

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
    }

    /**
     * Jobs de ciclo de vida: expiración al TTL + polling de confirmación.
     */
    private function despacharJobs(SolicitudReserva $solicitud): void
    {
        ExpirarSolicitudJob::dispatch($solicitud->id)->delay($solicitud->expira_en);

        $intervaloPolling = (int) ParametroSistema::where(
            'clave',
            'polling_intervalo_segundos'
        )->value('valor');

        PollingSolicitudJob::dispatch($solicitud->id)
            ->delay(now()->addSeconds($intervaloPolling ?: 20));
    }

    // ─── Cobro / respuesta ───────────────────────────────────────────────

    /**
     * Construye los items de canchas y delega la liquidación al helper central.
     *
     * La resolución de tarifa por campo/hora (CatalogoSirebService) sigue
     * siendo dominio de canchas y vive acá; lo que el helper concentra es el
     * contrato con SIREB + el shape de datos_cobro_pendiente + auditoría.
     *
     * NO atrapa excepciones: la política vive en crearLiquidacionConPolitica().
     *
     * @throws \Throwable si algo falla (lo captura el caller para decidir)
     */
    private function crearLiquidacionEnSireb(SolicitudReserva $solicitud): void
    {
        $items = [];

        foreach ($solicitud->detalles as $detalle) {
            // P8.2B: usar snapshot persistido en la línea. Evita que un cambio
            // posterior en el catálogo SIREB altere una liquidación en curso
            // o un reintento.
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
     *
     * RespuestaCobroDTO legacy: qr_image_base64 y checkout_url ya no aplican
     * en SIREB v1 (solo pago manual). El qr_string se arma localmente con el
     * codigo_publico para que el frontend renderice un QR escaneable/copiable
     * en ventanilla.
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
