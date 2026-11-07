<?php

namespace App\Services;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCreadaDTO;
use App\DTOs\SolicitudFiltrosDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Jobs\ExpirarSolicitudJob;
use App\Jobs\PollingSolicitudJob;
use App\Jobs\ReintentarSolicitudJob;
use App\Models\Auditoria;
use App\Models\Asociacion;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\ParametroSistema;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\ArancelVariante;
use App\Models\AsociacionAlumno;
use App\Models\SolicitudDetalleAlumno;
use App\Services\Sireb\SirebLiquidacionService;
use Carbon\Carbon;
use Generator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\DTOs\ServicioAdicionalDTO;
use Illuminate\Support\Str;

/**
 * Creación de solicitudes de reserva multi-franja (HU-D1, HU-D2, HU-D3, HU-D8).
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
    public const VENTANA_DIAS = 60;

    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    // [FIX #2 tz] Zona horaria oficial de Bolivia (UTC-4, sin DST). Todo el
    // path de creación calcula "hoy/ahora/día" en este tz explícito, sin
    // depender de APP_TIMEZONE del servidor (que en Docker suele ser UTC y
    // rompe la ventana de 20:00 a 23:59 hora local).
    private const TIMEZONE_BOLIVIA = 'America/La_Paz';

    public function __construct(
        private DisponibilidadService $disponibilidad,
        private AuditoriaService $auditoria,
        private CatalogoSirebService $catalogo,
        private SirebLiquidacionService $liquidaciones,
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
    ): SolicitudCreadaDTO
    {
        // ── Gate anti-hoarding: SOLO canal público ──
        // El mostrador (admin) crea a nombre del ciudadano desde la IP de la
        // oficina; aplicarle el tope por IP bloquearía la operación del GAD.
        // El resto (disponibilidad, EXCLUDE, precio espejo) se mantiene igual.
        if (! $esAdmin) {
            $this->validarAntiHoarding($datos, $convenioAsociacion);
        }

        // [convenio] Si el pagador es responsable de una asociación VIGENTE,
        // la solicitud es de convenio: plazo largo + tope por CI propio.
        // [Fase 6a] Si convenioAsociacion viene del token (no-null), BYPASSEA
        // la resolución desde el payload CI → el CI del pagador ya fue
        // sobrescrito por el controller con el del token.
        $asociacion = $convenioAsociacion ?? $this->resolverAsociacionVigente($datos->ciNitPagador);

        // [Fase 6a] Flag que abre el visibility gate de variantes de convenio
        // (visible_publico=false) sin flipo la flag. Solo true si el token
        // resolvió una asociación vigente.
        $puedeUsarNoVisible = $convenioAsociacion !== null;

        // ── Capa 1: verificación rápida (aplicativa) ──
        $campos = $this->verificarFranjas($datos->franjas);

        // ── P8.2B: resolver variante/tarifa/cantidad ANTES de persistir ──
        $franjasResueltas = $this->resolverFranjasParaCrear($datos->franjas, $campos, $esAdmin, $puedeUsarNoVisible, $convenioAsociacion);

        // [convenio-guard] variante de convenio solo pa' asociación VIGENTE del mismo tipo.
        $this->validarVariantesConvenio($franjasResueltas, $asociacion);

        // [Fase 3] alumnos asignados deben pertenecer a la asociacion del pagador.
        $this->validarAlumnosPertenencia($franjasResueltas, $asociacion);

        // ── P8.4B: hora de inicio más temprana por campo, para resolver
        // servicios con tarifa diurna/nocturna (ej. escenario) según cuándo
        // se usa el espacio. ──
        $horasPorCampo = [];
        foreach ($franjasResueltas as $r) {
            $cid = $r['franja']->campoId;
            $seg = self::aSegundos($r['franja']->horaInicio);
            if (! isset($horasPorCampo[$cid]) || $seg < $horasPorCampo[$cid]) {
                $horasPorCampo[$cid] = $seg;
            }
        }

        // ── P8.3B / P8.4B: resolver servicios adicionales ANTES de persistir ──
        $serviciosResueltos = $this->resolverServiciosParaCrear(
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
        $expiracionMinutos = $this->calcularExpiracionMinutos($asociacion, $esAdmin, $convenioAsociacion !== null);

        try {
            $solicitud = DB::transaction(function () use ($datos, $franjasResueltas, $serviciosResueltos, $montoTotal, $expiracionMinutos) {
                $solicitudNueva = SolicitudReserva::create([
                    'codigo_seguimiento' => $this->generarCodigoSeguimiento(),
                    'monto_total' => $montoTotal,
                    'nombre_pagador' => $this->normalizarPagador($datos->nombrePagador),
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

                    // [Fase 3] puente alumno<->franja. array_unique evita que un
                    // payload con el mismo alumno dos veces viole el unique
                    // (detalle_id, alumno_id). En regularizar es no-op ([]).
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
                    $this->verificarFranjas($datos->franjas);
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
            // Ventanilla: sin reintento silencioso. El funcionario frente al
            // ciudadano necesita el código YA o el error real. Rechazamos
            // inline (la franja se libera sola al salir de estado activo, el
            // EXCLUDE deja de contarla) y propagamos para que el controller
            // devuelva 422.
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
     * auditoría. Ver deuda anotada en la respuesta.
     *
     * @throws FranjaNoDisponibleException|ValidationException|\Throwable
     */
    public function regularizar(SolicitudReservaDTO $datos, string $motivo, Funcionario $funcionario): SolicitudCreadaDTO
    {
        // Capa 1 con gates de tiempo saltados (regularización).
        $campos = $this->verificarFranjas($datos->franjas, saltarGatesTiempo: true);

        $franjasResueltas = $this->resolverFranjasParaCrear($datos->franjas, $campos, esAdmin: true);

        $horasPorCampo = [];
        foreach ($franjasResueltas as $r) {
            $cid = $r['franja']->campoId;
            $seg = self::aSegundos($r['franja']->horaInicio);
            if (! isset($horasPorCampo[$cid]) || $seg < $horasPorCampo[$cid]) {
                $horasPorCampo[$cid] = $seg;
            }
        }

        $serviciosResueltos = $this->resolverServiciosParaCrear(
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
        $asociacion = $this->resolverAsociacionVigente($datos->ciNitPagador);
        // [convenio-fix #4] regularizar es admin-only (ventanilla) -> plazo largo si es asociacion.
        $expiracionMinutos = $this->calcularExpiracionMinutos($asociacion, true);

        try {
            $solicitud = DB::transaction(function () use ($datos, $franjasResueltas, $serviciosResueltos, $montoTotal, $expiracionMinutos) {
                $solicitudNueva = SolicitudReserva::create([
                    'codigo_seguimiento' => $this->generarCodigoSeguimiento(),
                    'monto_total' => $montoTotal,
                    'nombre_pagador' => $this->normalizarPagador($datos->nombrePagador),
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
                    $this->verificarFranjas($datos->franjas, saltarGatesTiempo: true);
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

        try {
            $this->crearLiquidacionEnSireb($solicitud);
        } catch (\Throwable $e) {
            // Política admin (ventanilla): sin reintento silencioso. Rechazamos
            // inline y propagamos para que el controller devuelva 422 con el
            // error real. La franja se libera sola al salir de estado activo.
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
     * contrato con SIREB + el shape de datos_cobro_pendiente + auditoría, que
     * antes estaba duplicado con el job de reintento.
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
            // Esto evita que un cambio posterior en el catálogo SIREB altere
            // una liquidación en curso o un reintento.
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
     * Compatible con SolicitudCreadaDTO sin depender del DTO de cobro.
     */
    private function construirRespuesta(SolicitudReserva $solicitud): SolicitudCreadaDTO
    {
        $datosCobro = $solicitud->datos_cobro_pendiente ?? [];

        // RespuestaCobroDTO legacy: qr_image_base64 y checkout_url ya no
        // aplican en SIREB v1 (solo pago manual). El qr_string se arma
        // localmente con el codigo_publico para que el frontend renderice
        // un QR escaneable/copiable en ventanilla.
        $respuestaCore = new RespuestaCobroDTO(
            referenciaRecaudaciones: $datosCobro['codigo_publico'] ?? $solicitud->codigo_seguimiento,
            qrString: $datosCobro['qr_string'] ?? null,
            qrImageBase64: null,
            checkoutUrl: null,
        );

        return new SolicitudCreadaDTO($solicitud, $respuestaCore);
    }

    /**
     * Capa 1: valida estado del campo, tarifa vigente, ventana de fechas,
     * horario de atención, disponibilidad de cada franja contra la grilla
     * y que la franja no haya comenzado aún.
     *
     * [FIX B77-B] $saltarGatesTiempo=true (regularización) omite validarVentana
     * y validarFranjaFutura, y hace que verificarFranjaEnGrilla tolere bloques
     * 'pasada'. La integridad de disponibilidad (campo activo, tarifas, unicidad,
     * solape interno, horario, EXCLUDE) NO se relaja.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @return array<string, array{tarifas: Collection<string, TarifaCampo>, inicio_noche: int}>
     */
    private function verificarFranjas(array $franjas, bool $saltarGatesTiempo = false): array
    {
        $campos = [];

        $grupos = collect($franjas)->groupBy(
            fn (FranjaSolicitadaDTO $f) => $f->campoId.'|'.$f->fecha,
        );

        foreach ($grupos as $grupo) {
            $primera = $grupo->first();

            $campo = CampoDeportivo::find($primera->campoId);
            if (! $campo || $campo->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'franjas' => 'El campo seleccionado no está disponible para reserva.',
                ]);
            }

            $tarifasActivas = $campo->tarifas()
                ->paraGrillaHoraria()
                ->get();

            if ($tarifasActivas->isEmpty()) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no tiene tarifa SIREB vigente espejada. Ejecutá la sincronización desde SIREB.",
                ]);
            }

            // P8.2B: un campo físico puede tener varias variantes/servicios SIREB.
            // La unicidad ya no es solo por clasificacion, sino por
            // servicio_sireb_id + clasificacion.
            $variantes = ArancelVariante::query()
                ->where('campo_id', $campo->id)
                ->where('activo', true)
                ->orderBy('orden')
                ->get()
                ->keyBy('id');

            $porServicioYClasificacion = $tarifasActivas->groupBy(
                fn (TarifaCampo $t) => ($t->servicio_sireb_id ?? 'legacy').'|'.$t->clasificacion
            );

            foreach ($porServicioYClasificacion as $clave => $coleccion) {
                if ($coleccion->count() > 1) {
                    [$servicio, $clasificacion] = array_pad(explode('|', (string) $clave, 2), 2, '');

                    throw ValidationException::withMessages([
                        'franjas' => "El campo {$campo->nombre} tiene más de una tarifa espejada activa para el servicio '{$servicio}' y clasificación '{$clasificacion}'. Revisá el tarifario en SIREB.",
                    ]);
                }
            }

            $campos[$campo->id] = [
                'campo' => $campo,
                'tarifas' => $tarifasActivas,
                'variantes' => $variantes,
                'inicio_noche' => self::aSegundos($campo->hora_inicio_noche ?? '18:00:00'),
            ];

            // [FIX #2 tz] Parsear la fecha pedida en hora Bolivia para que
            // dayOfWeek/toDateString (usados por la grilla) y la ventana
            // comparen siempre en el mismo tz.
            $fecha = Carbon::parse($primera->fecha, self::TIMEZONE_BOLIVIA)->startOfDay();

            // [FIX B77-B] en regularización se salta el gate de ventana (ayer/hoy válido).
            if (! $saltarGatesTiempo) {
                $this->validarVentana($fecha);
            }

            $this->validarSolapeInterno($grupo->all());

            $campo->load(['tarifas' => fn ($q) => $q->paraGrillaHoraria()]);

            $grilla = $this->disponibilidad->calcularGrilla($campo, $fecha);

            if (! $grilla['abierto']) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no abre el {$primera->fecha}.",
                ]);
            }

            foreach ($grupo as $franja) {
                // [FIX #1 franja pasada] Gate temporal ANTES de cruzar con la
                // grilla: si la franja ya comenzó (hora Bolivia), no se puede
                // reservar ni crear hold.
                // [FIX B77-B] en regularización se salta el gate de franja futura.
                if (! $saltarGatesTiempo) {
                    $this->validarFranjaFutura($franja);
                }

                $this->verificarFranjaEnGrilla($franja, $grilla, $saltarGatesTiempo);
            }
        }

        return $campos;
    }

    private function verificarFranjaEnGrilla(FranjaSolicitadaDTO $franja, array $grilla, bool $saltarGatesTiempo = false): void
    {
        $fInicio = self::aSegundos($franja->horaInicio);
        $fFin = self::aSegundos($franja->horaFin);

        if ($fFin <= $fInicio) {
            throw ValidationException::withMessages([
                'franjas' => 'La hora de fin debe ser posterior a la hora de inicio.',
            ]);
        }

        $solapados = collect($grilla['bloques'])->filter(
            fn (array $b) => self::aSegundos($b['hora_inicio']) < $fFin
                && self::aSegundos($b['hora_fin']) > $fInicio,
        );

        if ($solapados->isEmpty()) {
            throw ValidationException::withMessages([
                'franjas' => "La franja {$franja->horaInicio}-{$franja->horaFin} está fuera del horario de atención.",
            ]);
        }

        // [FIX B77-B] 'pasada' se tolera SOLO en regularización: una franja de hoy ya
        // corrida que nadie tomó está físicamente libre; el EXCLUDE (capa 2) sigue
        // bloqueando si hubiera solicitud activa detrás. 'ocupada'/'bloqueada_temporal'
        // NUNCA se toleran (no regularizo sobre un slot tomado).
        $tolerados = $saltarGatesTiempo ? ['libre', 'pasada'] : ['libre'];

        if ($solapados->contains(fn (array $b) => ! in_array($b['estado'], $tolerados, true))) {
            throw new FranjaNoDisponibleException(
                "La franja {$franja->horaInicio}-{$franja->horaFin} del {$franja->fecha} ya no está disponible.",
                [
                    'campo_id' => $franja->campoId,
                    'fecha' => $franja->fecha,
                    'hora_inicio' => $franja->horaInicio,
                    'hora_fin' => $franja->horaFin,
                ],
            );
        }
    }

    /**
     * [FIX #1 franja pasada] Rechaza franjas cuyo inicio ya pasó según el
     * reloj oficial de Bolivia. Compara el datetime (fecha + hora_inicio) en
     * America/La_Paz contra ahoraBolivia(), de modo que el resultado no
     * depende del tz del servidor.
     *
     * Regla: no se puede reservar una franja que ya comenzó (incluso en este
     * mismo segundo). Una franja que empieza dentro de unos minutos SÍ se
     * permite; si el PO quiere exigir margen de anticipación, subir el
     * umbral en MARGEN_ANTICIPACION_MINUTOS (ver más abajo).
     */
    private function validarFranjaFutura(FranjaSolicitadaDTO $franja): void
    {
        $inicioFranja = Carbon::parse(
            "{$franja->fecha} {$franja->horaInicio}",
            self::TIMEZONE_BOLIVIA,
        );

        // Margen opcional (default 0 = basta que no haya comenzado).
        $umbral = $this->ahoraBolivia()->addMinutes(self::MARGEN_ANTICIPACION_MINUTOS);

        if ($inicioFranja->lessThanOrEqualTo($umbral)) {
            throw ValidationException::withMessages([
                'franjas' => "La franja {$franja->horaInicio}-{$franja->horaFin} del {$franja->fecha} ya comenzó. Elegí un horario futuro.",
            ]);
        }
    }

    /**
     * [FIX #1] Minutos de anticipación mínima exigidos para reservar una
     * franja. 0 = se puede reservar hasta el segundo en que empieza. Subilo
     * (ej. 15) si el PO quiere garantizar tiempo de pago antes del inicio.
     */
    private const MARGEN_ANTICIPACION_MINUTOS = 0;

    /**
     * @param  FranjaSolicitadaDTO[]  $franjas
     */
    private function validarSolapeInterno(array $franjas): void
    {
        $total = count($franjas);

        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $aIni = self::aSegundos($franjas[$i]->horaInicio);
                $aFin = self::aSegundos($franjas[$i]->horaFin);
                $bIni = self::aSegundos($franjas[$j]->horaInicio);
                $bFin = self::aSegundos($franjas[$j]->horaFin);

                if ($aIni < $bFin && $aFin > $bIni) {
                    throw ValidationException::withMessages([
                        'franjas' => 'Hay franjas superpuestas dentro de la misma solicitud.',
                    ]);
                }
            }
        }
    }

    private function validarVentana(Carbon $fecha): void
    {
        // [FIX #2 tz] "hoy" en hora Bolivia, no en tz del servidor.
        $hoy = $this->ahoraBolivia()->startOfDay();

        if ($fecha->lt($hoy) || $fecha->gt($hoy->copy()->addDays(self::VENTANA_DIAS))) {
            throw ValidationException::withMessages([
                'franjas' => 'La fecha debe estar entre hoy y 60 días en el futuro.',
            ]);
        }
    }

    /**
     * P8.2B: resuelve variante/tarifa/cantidad para cada franja antes de persistir.
     *
     * Esto permite guardar snapshot en solicitud_reserva_detalle y que la
     * liquidación SIREB use exactamente la tarifa elegida, no una resolución
     * posterior que podría cambiar con el tiempo.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @return array<int, array<string, mixed>>
     */
    private function resolverFranjasParaCrear(array $franjas, array $campos, bool $esAdmin, bool $puedeUsarNoVisible = false, ?Asociacion $convenioAsociacion = null): array
    {
        $resueltas = [];

        foreach ($franjas as $franja) {
            $resuelta = $this->resolverTarifaParaFranja(
                $franja,
                $campos[$franja->campoId],
                $esAdmin,
                $puedeUsarNoVisible,
                $convenioAsociacion
            );

            $resuelta['franja'] = $franja;
            $resuelta['subtotal'] = (float) $resuelta['precio_unitario'] * (int) $resuelta['cantidad'];

            $resueltas[] = $resuelta;
        }

        return $resueltas;
    }

    /**
     * Resuelve la tarifa SIREB aplicable a una franja.
     *
     * Reglas:
     * - Si la franja trae variante_id, usa las tarifas de esa variante.
     * - Si no trae variante_id y el campo tiene variantes activas sin servicio
     *   base, exige variante.
     * - Si no trae variante_id y el campo es legacy, mantiene comportamiento actual.
     * - Público solo puede usar variantes con visible_publico = true.
     *
     * @return array{
     *     variante_id: ?string,
     *     servicio_sireb_id: ?string,
     *     tarifa_sireb_id: string,
     *     etiqueta_sireb: ?string,
     *     cantidad: int,
     *     precio_unitario: float
     * }
     */
    private function resolverTarifaParaFranja(FranjaSolicitadaDTO $franja, array $campoInfo, bool $esAdmin, bool $puedeUsarNoVisible = false, ?Asociacion $convenioAsociacion = null): array
    {
        $variante = null;
        $tarifas = $campoInfo['tarifas'];

        if (filled($franja->varianteId)) {
            $variante = $campoInfo['variantes']->get($franja->varianteId);

            if (! $variante) {
                throw ValidationException::withMessages([
                    'franjas' => "La variante seleccionada no pertenece al campo {$campoInfo['campo']->nombre}.",
                ]);
            }

            // [Fase 6a] puedeUsarNoVisible abre el gate para el asociado logueado
            // online SIN flipo la flag visible_publico. El anónimo sigue bloqueado.
            if (! $esAdmin && ! $puedeUsarNoVisible && ! $variante->visible_publico) {
                throw ValidationException::withMessages([
                    'franjas' => "La variante {$variante->nombre} solo está disponible en ventanilla.",
                ]);
            }

            if ($variante->unidad_medida !== 'hora') {
                throw ValidationException::withMessages([
                    'franjas' => "La variante {$variante->nombre} no es reservable por franja horaria en esta etapa.",
                ]);
            }

            if (blank($variante->servicio_sireb_id)) {
                throw ValidationException::withMessages([
                    'franjas' => "La variante {$variante->nombre} no está vinculada a SIREB. Ejecutá la sincronización.",
                ]);
            }

            $tarifas = $tarifas->where('servicio_sireb_id', $variante->servicio_sireb_id);
        } else {
            // [Fase 6b] Auto-resolución de variante de convenio para asociación
            // logueada que NO mandó variante_id explícito: el token ya validó la
            // asociación (convenioAsociacion no-null) y su tipo determina cuál
            // variante le corresponde. Solo se auto-elige si hay EXACTAMENTE UNA
            // variante horaria activa, vinculada a SIREB, cuyo tipo_asociacion
            // coincide con el tipo de la asociación. Cero o múltiples coincidencias
            // -> NO se auto-resuelve: cae al logic original (422 "requiere
            // seleccionar variante" o legacy), nunca se elige una variante de
            // precio equivocado.
            $autoVariante = $this->autoResolverVarianteConvenio(
                $campoInfo['variantes'],
                $convenioAsociacion,
            );

            if ($autoVariante !== null) {
                $variante = $autoVariante;
                $tarifas = $tarifas->where('servicio_sireb_id', $variante->servicio_sireb_id);
            } else {
                // Si el campo tiene variantes arancelarias y no tiene servicio base,
                // no se puede adivinar cuál cobrar.
                if ($campoInfo['variantes']->isNotEmpty() && blank($campoInfo['campo']->servicio_sireb_id)) {
                    throw ValidationException::withMessages([
                        'franjas' => "El campo {$campoInfo['campo']->nombre} requiere seleccionar una variante arancelaria.",
                    ]);
                }

                // Legacy: si el campo tiene servicio base, priorizamos tarifas de ese
                // servicio. Si no hay coincidencia (datos viejos sin servicio_sireb_id
                // en la tarifa), caemos al comportamiento actual.
                if (filled($campoInfo['campo']->servicio_sireb_id)) {
                    $filtradas = $tarifas->where('servicio_sireb_id', $campoInfo['campo']->servicio_sireb_id);

                    if ($filtradas->isNotEmpty()) {
                        $tarifas = $filtradas;
                    }
                } elseif ($tarifas->whereNotNull('servicio_sireb_id')->isNotEmpty()) {
                    // [B92-a] campo desvinculado (sin servicio_sireb_id) y sin variantes,
                    // pero con tarifas espejadas que apuntan a un servicio (huérfanas de un
                    // vínculo roto: 305 no las purga al desvincular). El invariante 862
                    // "reservable ⟺ servicio_sireb_id con tarifa" no se cumple → bloqueo pa'
                    // que no reserve/cobre la tarifa vieja. Las tarifas legacy (servicio_sireb_id
                    // null en la fila, :796-797) NO se bloquean: son datos viejos sin servicio,
                    // no huérfanas de un vínculo roto; caen al comportamiento actual.
                    throw ValidationException::withMessages([
                        'franjas' => "El campo {$campoInfo['campo']->nombre} no está vinculado a SIREB pero tiene tarifas espejadas de un servicio. Vinculalo o ejecutá la sincronización.",
                    ]);
                }
            }
        }

        if ($tarifas->isEmpty()) {
            throw ValidationException::withMessages([
                'franjas' => "No hay tarifa SIREB vigente espejada para la selección del campo {$campoInfo['campo']->nombre}.",
            ]);
        }

        $unica = $tarifas->firstWhere('clasificacion', 'unica');

        if ($unica) {
            $tarifa = $unica;
        } else {
            $tipo = self::aSegundos($franja->horaInicio) < $campoInfo['inicio_noche']
                ? 'diurna'
                : 'nocturna';

            $tarifa = $tarifas->firstWhere('clasificacion', $tipo);

            if (! $tarifa) {
                throw ValidationException::withMessages([
                    'franjas' => "No hay tarifa {$tipo} vigente espejada para la franja solicitada.",
                ]);
            }
        }

        // P8.2B: en variantes la tarifa SIREB es obligatoria porque el cobro
        // depende de la variante elegida. En campos legacy permitimos null y
        // dejamos que la liquidación caiga al resolvedor viejo: público queda
        // pendiente con reintento, ventanilla rechaza ruidoso. Así no rompemos
        // HU-D8 ni los fixtures viejos.
        if ($variante !== null && blank($tarifa->tarifa_sireb_id)) {
            throw ValidationException::withMessages([
                'franjas' => "La variante {$variante->nombre} no tiene tarifa SIREB espejada. Ejecutá la sincronización.",
            ]);
        }

        return [
            'variante_id' => $variante?->id,
            'servicio_sireb_id' => $tarifa->servicio_sireb_id ?? $variante?->servicio_sireb_id,
            // Legacy puede quedar null; variantes ya fueron validadas arriba.
            'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
            'etiqueta_sireb' => $tarifa->etiqueta_sireb,
            'cantidad' => 1,
            'precio_unitario' => (float) $tarifa->precio_por_hora,
        ];
    }

    /**
     * [Fase 6b] Elige la única variante de convenio que le corresponde a una
     * asociación logueada cuando el front no mandó variante_id explícito.
     *
     * Devuelve null (=> NO se auto-resuelve, cae al logic original) si:
     *  - no hay convenioAsociacion (anónimo / ventanilla / ciudadano no asociado);
     *  - la asociación no tiene tipo definido;
     *  - no hay EXACTAMENTE UNA variante horaria activa, vinculada a SIREB, cuyo
     *    tipo_asociacion coincida con el tipo de la asociación.
     *
     * Nunca devuelve una variante ambigua: ante 0 o >1 coincidencias retorna null,
     * así que el precio que se cobra es siempre el de LA variante que matchea el
     * tipo de la asociación, o error ruidoso (422), jamás un precio equivocado.
     */
    private function autoResolverVarianteConvenio(Collection $variantes, ?Asociacion $convenioAsociacion): ?ArancelVariante
    {
        if ($convenioAsociacion === null) {
            return null;
        }

        $tipoAsoc = $convenioAsociacion->tipo?->value;
        if (blank($tipoAsoc)) {
            return null;
        }

        $candidatas = $variantes->filter(
            fn (ArancelVariante $v) => $v->activo
                && $v->unidad_medida === 'hora'
                && $v->tipo_asociacion === $tipoAsoc
                && filled($v->servicio_sireb_id)
        );

        return $candidatas->count() === 1 ? $candidatas->first() : null;
    }

    /**
     * P8.3B: resuelve servicios adicionales antes de persistir.
     *
     * Reglas:
     * - El servicio debe pertenecer a un campo presente en las franjas.
     * - La variante debe estar activa.
     * - No se aceptan variantes horarias como servicio adicional.
     * - Público solo puede usar servicios visibles públicamente.
     * - Debe tener servicio_sireb_id y tarifa SIREB espejada.
     *
     * @param  ServicioAdicionalDTO[]  $servicios
     * @return array<int, array<string, mixed>>
     */
    private function resolverServiciosParaCrear(
        array $servicios,
        array $campos,
        bool $esAdmin,
        array $horasPorCampo = [],
        bool $puedeUsarNoVisible = false,
    ): array {
        $resueltos = [];

        foreach ($servicios as $servicio) {
            if (! isset($campos[$servicio->campoId])) {
                throw ValidationException::withMessages([
                    'servicios' => 'Los servicios adicionales deben corresponder a un campo incluido en la reserva.',
                ]);
            }

            $campoInfo = $campos[$servicio->campoId];

            $variante = $campoInfo['variantes']->get($servicio->varianteId);

            if (! $variante) {
                throw ValidationException::withMessages([
                    'servicios' => "La variante del servicio adicional no pertenece al campo {$campoInfo['campo']->nombre}.",
                ]);
            }

            if ($variante->unidad_medida === 'hora') {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} no puede agregarse como servicio adicional porque es una variante horaria.",
                ]);
            }

            if (! $esAdmin && ! $puedeUsarNoVisible && ! $variante->visible_publico) {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} solo está disponible en ventanilla.",
                ]);
            }

            if (blank($variante->servicio_sireb_id)) {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} no está vinculado a SIREB. Ejecutá la sincronización.",
                ]);
            }

            $tarifas = TarifaCampo::query()
                ->where('campo_id', $variante->campo_id)
                ->where('servicio_sireb_id', $variante->servicio_sireb_id)
                ->whereNull('vigente_hasta')
                ->where('liquidable', true)
                ->where('tarifario_estado', 'vigente')
                ->get();

            if ($tarifas->isEmpty()) {
                throw ValidationException::withMessages([
                    'servicios' => "No hay tarifa SIREB vigente espejada para el servicio {$variante->nombre}.",
                ]);
            }

            $unica = $tarifas->firstWhere('clasificacion', 'unica');

            if ($unica) {
                $tarifa = $unica;
            } elseif ($tarifas->count() === 1) {
                $tarifa = $tarifas->first();
            } else {
                // P8.4B: servicios con tarifa diurna/nocturna (ej. escenario)
                // se resuelven por la hora de la franja del mismo campo, igual
                // que una franja horaria. Sin franja asociada no se puede
                // adivinar el tramo: error ruidoso, no silencio.
                $horaSeg = $horasPorCampo[$servicio->campoId] ?? null;

                if ($horaSeg === null) {
                    throw ValidationException::withMessages([
                        'servicios' => "El servicio {$variante->nombre} se cobra por tramo (diurno/nocturno) y no hay franja horaria en el mismo campo para resolverlo.",
                    ]);
                }

                $tipo = $horaSeg < $campoInfo['inicio_noche'] ? 'diurna' : 'nocturna';
                $tarifa = $tarifas->firstWhere('clasificacion', $tipo);

                if (! $tarifa) {
                    throw ValidationException::withMessages([
                        'servicios' => "No hay tarifa {$tipo} vigente espejada para el servicio {$variante->nombre}.",
                    ]);
                }
            }

            if (blank($tarifa->tarifa_sireb_id)) {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} no tiene tarifa SIREB espejada. Ejecutá la sincronización.",
                ]);
            }

            $cantidad = max(1, (int) $servicio->cantidad);
            $precioUnitario = (float) $tarifa->precio_por_hora;
            $subtotal = round($precioUnitario * $cantidad, 2);

            $resueltos[] = [
                'servicio' => $servicio,
                'campo_id' => $variante->campo_id,
                'variante_id' => $variante->id,
                'servicio_sireb_id' => $variante->servicio_sireb_id,
                'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                'etiqueta_sireb' => $tarifa->etiqueta_sireb,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
            ];
        }

        return $resueltos;
    }

    private function minutosExpiracion(): int
    {
        $valor = ParametroSistema::query()
            ->where('clave', 'solicitud_reserva_expiracion_minutos')
            ->value('valor');

        return (int) ($valor ?? 15);
    }

    /**
     * [convenio] Lookup por el CI/NIT del responsable que reserva. Vigente =
     * activo + convenio sin vencer. Devuelve null si el pagador NO es asociación
     * (-> flujo público normal, plazo 15 min, tope por CI bajo).
     * [GAD] hoy buscamos solo por responsable_ci; si quieren que también se
     * reconozca por el NIT de la asociación, sumar ->orWhere('nit', $ciNit).
     */
    private function resolverAsociacionVigente(?string $ciNit): ?Asociacion
    {
        return app(AsociacionVigenteResolver::class)->porCi($ciNit);
    }

    /**
     * [convenio] Plazo de hold: público = minutos (15 default); asociación
     * vigente = DÍAS (ventanilla + boleta, el pago no es online). El plazo NO
     * se espeja en tarifas_campo (espejar() no guarda plazo_pago_dias), así que
     * vive en ParametroSistema para que el GAD lo ajuste sin redeploy.
     */
    private function calcularExpiracionMinutos(?Asociacion $asociacion, bool $esAdmin, bool $convenioOnline = false): int
    {
        // [convenio-fix #4] El plazo largo (boleta en ventanilla que "queda a la
        // espera de validacion") es del CANAL ventanilla, no del atributo "es
        // asociacion". Una asociacion que reserva por web-public paga online con
        // QR (sin boleta) -> plazo publico normal. Bifurca por canal, no por
        // identidad (alineado al plan de Fase 2: "convenio = ventanilla+boleta").
        //
        // [Fase 6a] Excepción controlada: el asociado LOGUEADO online
        // ($convenioOnline=true) obtiene plazo largo PERO pagando por QR (no
        // boleta). Su identidad ya fue validada por el token Sanctum y
        // re-validada por AsociacionVigenteResolver al reservar
        // (ResolveCiudadanoOpcional): la misma garantía que el mostrador
        // presencial, por otra vía. El anónimo con CI tipeado sigue 15 min
        // ($convenioOnline=false), y el asociado cuya asociación venció entre
        // login y reserva también (convenioAsociacion=null -> asociacion=null).
        if ($asociacion === null || (! $esAdmin && ! $convenioOnline)) {
            return $this->minutosExpiracion();
        }

        $dias = (int) (ParametroSistema::where('clave', 'convenio_expiracion_dias')->value('valor') ?: 3);

        return max(1, $dias) * 24 * 60;
    }

    /**
     * Gate anti-hoarding: 3 chequeos que corren ANTES de crear el hold.
     * Todos leen de parametros_sistema para que el GAD los ajuste sin redeploy.
     *
     *  M1 dinámico: techo de franjas por solicitud (el validator ya corta en 6).
     *  M2: techo de solicitudes pendientes activas por IP.
     *  M3: IP en cooldown por expiraciones repetidas.
     *
     * @throws ValidationException
     */
    private function validarAntiHoarding(SolicitudReservaDTO $datos, ?Asociacion $asociacionConocida = null): void
    {
        // [Fase 2b] Tope por IDENTIDAD REAL (CI/NIT), no solo por IP.
        // Corre SIEMPRE (antes del return de IP) porque la identidad es el CI,
        // no la máquina. A la asociación vigente se le da un tope ALTO (o 0 =
        // sin tope) porque el presidente acumula franjas para sus alumnos; al
        // vecino suelto, el tope bajo anti-acaparamiento.
        if (filled($datos->ciNitPagador)) {
            // [Fase 6a] Si el token ya resolvió la asociación vigente
            // ($asociacionConocida no-null), la reusamos: evita la doble query
            // y honra la re-validación del middleware (asociación vencida entre
            // login y reserva -> convenioAsociacion=null -> resolvemos del
            // payload como hoy, y da null -> tope bajo + plazo público).
            $asociacion = $asociacionConocida ?? $this->resolverAsociacionVigente($datos->ciNitPagador);
            $maxPorCi = $asociacion
                ? (int) (ParametroSistema::where('clave', 'max_pendientes_por_asociacion')->value('valor') ?: 0)
                : (int) (ParametroSistema::where('clave', 'max_pendientes_por_ci')->value('valor') ?: 3);

            if ($maxPorCi > 0) {
                $pendientesCi = SolicitudReserva::where('ci_nit_pagador', $datos->ciNitPagador)
                    ->where('estado', EstadoSolicitudReserva::Pendiente)
                    ->where('expira_en', '>', now())
                    ->count();

                if ($pendientesCi >= $maxPorCi) {
                    throw ValidationException::withMessages([
                        'franjas' => $asociacion
                            ? "La asociación ya tiene {$pendientesCi} reservas pendientes. Liquidá alguna antes de generar más."
                            : "Tenés {$pendientesCi} reservas pendientes por pagar. Confirmá o esperá a que expiren antes de crear una nueva.",
                    ]);
                }
            }
        }

        if (! $datos->ipCobrador) {
            return; // sin IP no se puede aplicar (no debería pasar con TrustProxies)
        }

        // ── M1 dinámico: tope configurable ──
        $maxFranjas = (int) ParametroSistema::where('clave', 'max_franjas_por_solicitud')->value('valor') ?: 6;
        if (count($datos->franjas) > $maxFranjas) {
            throw ValidationException::withMessages([
                'franjas' => "No podés reservar más de {$maxFranjas} franjas en una sola solicitud.",
            ]);
        }

        // ── M3: cooldown por expiraciones (se chequea antes que M2, es más barato) ──
        $cooldownKey = $this->cooldownKey($datos->ipCobrador);
        if (Cache::has($cooldownKey)) {
            $minutosRestantes = (int) Cache::get($cooldownKey, 0);
            throw ValidationException::withMessages([
                'franjas' => "Esta IP fue bloqueada temporalmente por reservas sin pagar. Intentá nuevamente en {$minutosRestantes} minutos.",
            ]);
        }

        // ── M2: tope de pendientes activas por IP ──
        $maxPendientes = (int) ParametroSistema::where('clave', 'max_pendientes_por_ip')->value('valor') ?: 3;
        $pendientesActivas = SolicitudReserva::where('ip_cobrador', $datos->ipCobrador)
            ->where('estado', EstadoSolicitudReserva::Pendiente)
            ->where('expira_en', '>', now())
            ->count();

        if ($pendientesActivas >= $maxPendientes) {
            throw ValidationException::withMessages([
                'franjas' => "Tenés {$pendientesActivas} reservas pendientes por pagar. Confirmá o esperá a que expiren antes de crear una nueva.",
            ]);
        }
    }

    /**
     * [convenio-guard] Ninguna franja resuelta puede usar una variante con
     * tipo_asociacion filled salvo que el pagador sea asociación VIGENTE del
     * MISMO tipo. Cierra el abuso del dialog de ventanilla (elegir convenio a
     * mano para un CI que no lo es). Corre despues de resolverFranjasParaCrear.
     */
    private function validarVariantesConvenio(array $franjasResueltas, ?Asociacion $asociacion): void
    {
        foreach ($franjasResueltas as $resuelta) {
            $vid = $resuelta['variante_id'] ?? null;
            if (blank($vid)) {
                continue;
            }
            $variante = ArancelVariante::find($vid);
            if ($variante && filled($variante->tipo_asociacion)) {
                $tipoAsoc = $asociacion?->tipo?->value; // cast enum -> string|null
                if ($tipoAsoc !== $variante->tipo_asociacion) {
                    throw ValidationException::withMessages([
                        'franjas' => "La variante '{$variante->nombre}' es de convenio ({$variante->tipo_asociacion}) y el CI/NIT informado no corresponde a una asociación vigente de ese tipo.",
                    ]);
                }
            }
        }
    }

    /**
     * [Fase 3] Si alguna franja trae alumnos, la asociacion del pagador debe
     * existir (vigente) y CADA alumno debe pertenecer a ella. Corre antes del
     * transaction, simetrico a validarVariantesConvenio. En regularizar es
     * no-op (el DTO trae alumnosIds=[] porque StoreRegularizacionRequest no lo
     * valida), entonces no frena la regularizacion.
     */
    private function validarAlumnosPertenencia(array $franjasResueltas, ?Asociacion $asociacion): void
    {
        $ids = [];
        foreach ($franjasResueltas as $resuelta) {
            foreach ($resuelta['franja']->alumnosIds as $id) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        if ($asociacion === null) {
            throw ValidationException::withMessages([
                'franjas' => 'Solo una asociación vigente puede asignar alumnos a una franja.',
            ]);
        }

        $unicos = array_values(array_unique($ids));
        $pertenecen = AsociacionAlumno::whereIn('id', $unicos)
            ->where('asociacion_id', $asociacion->id)
            ->pluck('id')
            ->map(fn ($v) => (string) $v)
            ->all();

        if (array_diff($unicos, $pertenecen) !== []) {
            throw ValidationException::withMessages([
                'franjas' => 'Uno o más alumnos asignados no pertenecen a la asociación del pagador.',
            ]);
        }
    }

    private function cooldownKey(string $ip): string
    {
        return 'hoard:cooldown:'.md5($ip);
    }

    private function generarCodigoSeguimiento(): string
    {
        do {
            $aleatorio = '';
            for ($i = 0; $i < 6; $i++) {
                $aleatorio .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
            $codigo = 'RES-'.now()->format('Ymd').'-'.$aleatorio;
        } while (SolicitudReserva::where('codigo_seguimiento', $codigo)->exists());

        return $codigo;
    }

    private static function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }

    /**
     * [FIX #2 tz] Reloj oficial de Bolivia, independiente de APP_TIMEZONE.
     */
    private function ahoraBolivia(): Carbon
    {
        return Carbon::now(self::TIMEZONE_BOLIVIA);
    }

    /**
     * [FIX B65 cancha] Normaliza nombre del pagador: trim + title case, igual que
     * piscina. Sin esto el listado muestra "MILTON DANIEL" (cancha) vs "daniel
     * hipamo" (piscina) con casing divergente.
     */
    private function normalizarPagador(?string $nombre): ?string
    {
        if ($nombre === null) {
            return null;
        }
        $trim = trim($nombre);
        if ($trim === '') {
            return null;
        }
        return Str::title(Str::lower($trim));
    }

    public function esAdministrador(Funcionario $funcionario): bool
    {
        return $funcionario->tienePermiso('*')
            || in_array($funcionario->rol?->nombre, ['admin_parametricas', 'admin_reservas'], true);
    }

    public function listarAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): \Illuminate\Pagination\LengthAwarePaginator
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        return $this->consultaBaseAdmin($filtros, $funcionario)
            ->paginate(
                $filtros->perPage,
                ['*'],
                'page',
                $filtros->page
            );
    }

    public function calcularStatsAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): array
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        $query = $this->consultaBaseAdmin($filtros, $funcionario, incluirEstado: false);

        $pendiente = EstadoSolicitudReserva::Pendiente->value;
        $confirmada = EstadoSolicitudReserva::Confirmada->value;
        $expirada = EstadoSolicitudReserva::Expirada->value;
        $cancelada = EstadoSolicitudReserva::Cancelada->value;
        $rechazada = EstadoSolicitudReserva::Rechazada->value;

        $stats = $query->getQuery()
            ->clone()
            ->reorder()
            ->selectRaw("
                count(*) as total,
                count(*) filter (where estado = '{$pendiente}') as pendientes,
                count(*) filter (where estado = '{$confirmada}') as confirmadas,
                count(*) filter (where estado = '{$expirada}') as expiradas,
                count(*) filter (where estado = '{$cancelada}') as canceladas,
                count(*) filter (where estado = '{$rechazada}') as rechazadas,
                coalesce(sum(monto_confirmado) filter (where estado = '{$confirmada}'), 0) as monto_confirmado
            ")
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'pendientes' => (int) ($stats->pendientes ?? 0),
            'confirmadas' => (int) ($stats->confirmadas ?? 0),
            'expiradas' => (int) ($stats->expiradas ?? 0),
            'canceladas' => (int) ($stats->canceladas ?? 0),
            'rechazadas' => (int) ($stats->rechazadas ?? 0),
            'monto_confirmado' => (float) ($stats->monto_confirmado ?? 0),
        ];
    }

    /**
     * Genera filas CSV aplanadas: una fila por ítem.
     *
     * P8.3E: ahora incluye tanto franjas horarias como servicios adicionales.
     *
     * Usa lazy/chunk para no cargar todo el dataset en memoria.
     *
     * @return Generator<int, array<int, string>>
     */
    public function exportarCsv(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): Generator
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        $query = $this->consultaBaseAdmin($filtros, $funcionario);

        yield [
            'codigo_seguimiento',
            'estado',
            'nombre_pagador',
            'telefono_pagador',
            'ci_nit_pagador',
            'codigo_reserva',
            'campo_nombre',
            'fecha_reserva',
            'hora_inicio',
            'hora_fin',
            'monto_pagado',
            'asistencia_marcada_en',
            'tipo_item',
            'variante_nombre',
            'servicio_sireb_codigo',
            'etiqueta_sireb',
            'cantidad',
            'precio_unitario',
            'subtotal',
        ];

        foreach ($query->lazy(500) as $solicitud) {
            foreach ($solicitud->detalles as $detalle) {
                $reserva = $detalle->reserva;

                $montoPagado = '';

                if ($reserva && $reserva->monto_pagado !== null) {
                    $montoPagado = number_format((float) $reserva->monto_pagado, 2, '.', '');
                }

                $cantidad = (int) ($detalle->cantidad ?? 1);
                $precioUnitario = (float) $detalle->tarifa_aplicada;
                $subtotal = round($precioUnitario * $cantidad, 2);

                yield [
                    (string) $solicitud->codigo_seguimiento,
                    $solicitud->estado->value,
                    (string) $solicitud->nombre_pagador,
                    (string) $solicitud->telefono_pagador,
                    (string) $solicitud->ci_nit_pagador,
                    (string) ($reserva?->codigo_reserva ?? ''),
                    (string) ($detalle->campo?->nombre ?? ''),
                    (string) ($detalle->fecha_reserva?->format('Y-m-d') ?? ''),
                    (string) substr((string) $detalle->hora_inicio, 0, 5),
                    (string) substr((string) $detalle->hora_fin, 0, 5),
                    $montoPagado,
                    (string) ($reserva?->asistencia_marcada_en?->format('Y-m-d H:i:s') ?? ''),
                    'franja',
                    (string) ($detalle->variante?->nombre ?? ''),
                    (string) ($detalle->variante?->servicio_sireb_codigo ?? $detalle->campo?->servicio_sireb_codigo ?? ''),
                    (string) ($detalle->etiqueta_sireb ?? ''),
                    (string) $cantidad,
                    number_format($precioUnitario, 2, '.', ''),
                    number_format($subtotal, 2, '.', ''),
                ];
            }

            foreach ($solicitud->servicios as $servicio) {
                $cantidad = (int) ($servicio->cantidad ?? 1);
                $precioUnitario = (float) $servicio->precio_unitario;
                $subtotal = (float) $servicio->subtotal;

                yield [
                    (string) $solicitud->codigo_seguimiento,
                    $solicitud->estado->value,
                    (string) $solicitud->nombre_pagador,
                    (string) $solicitud->telefono_pagador,
                    (string) $solicitud->ci_nit_pagador,
                    '',
                    (string) ($servicio->campo?->nombre ?? ''),
                    '',
                    '',
                    '',
                    '',
                    '',
                    'servicio',
                    (string) ($servicio->variante?->nombre ?? ''),
                    (string) ($servicio->variante?->servicio_sireb_codigo ?? ''),
                    (string) ($servicio->etiqueta_sireb ?? ''),
                    (string) $cantidad,
                    number_format($precioUnitario, 2, '.', ''),
                    number_format($subtotal, 2, '.', ''),
                ];
            }
        }
    }

    /**
     * @return array{solicitud: SolicitudReserva, auditoria: Collection<int, Auditoria>}
     */
    public function obtenerAdmin(string $id, Funcionario $funcionario): array
    {
        $esAdmin = $this->esAdministrador($funcionario);

        $solicitud = SolicitudReserva::with([
            'detalles',
            'detalles.campo',
            'detalles.variante',
            'detalles.reserva',
            'servicios',
            'servicios.campo',
            'servicios.variante',
        ])->find($id);

        if (! $solicitud) {
            abort(404);
        }

        if (! $esAdmin) {
            $tieneAcceso = $solicitud->detalles()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->exists();

            if (! $tieneAcceso) {
                abort(403);
            }

            $detallesVisibles = $solicitud->detalles()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->with(['campo', 'variante', 'reserva'])
                ->get();

            $solicitud->setRelation('detalles', $detallesVisibles);

            // P8.3D: funcionario_control solo ve servicios de campos asignados.
            $serviciosVisibles = $solicitud->servicios()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->with(['campo', 'variante'])
                ->get();

            $solicitud->setRelation('servicios', $serviciosVisibles);
        }

        $auditoria = Auditoria::where('tabla', 'solicitudes_reserva')
            ->where('registro_id', $solicitud->id)
            ->with('usuario')
            ->orderBy('fecha')
            ->get();

        return [
            'solicitud' => $solicitud,
            'auditoria' => $auditoria,
        ];
    }

    private function forzarFiltrosParaFuncionarioControl(
        SolicitudFiltrosDTO $filtros,
        Funcionario $funcionario
    ): void {
        if (! $this->esAdministrador($funcionario)) {
            $filtros->funcionarioControlId = $funcionario->id;
        }
    }

        private function consultaBaseAdmin(
        SolicitudFiltrosDTO $filtros,
        Funcionario $funcionario,
        bool $incluirEstado = true
    ): Builder {
        $esAdmin = $this->esAdministrador($funcionario);

        $query = SolicitudReserva::query();

        if ($esAdmin) {
            $query->with([
                'detalles',
                'detalles.campo',
                'detalles.variante',
                'detalles.reserva',
                'servicios',
                'servicios.campo',
                'servicios.variante',
            ]);
        } else {
            $query->with([
                'detalles' => function ($q) use ($funcionario) {
                    $q->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                        $aq->where('funcionario_id', $funcionario->id);
                    });
                },
                'detalles.campo',
                'detalles.variante',
                'detalles.reserva',
                'servicios' => function ($q) use ($funcionario) {
                    $q->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                        $aq->where('funcionario_id', $funcionario->id);
                    });
                },
                'servicios.campo',
                'servicios.variante',
            ]);
        }

        if ($incluirEstado && $filtros->estado) {
            $query->where('estado', $filtros->estado);
        }

        if ($filtros->desde) {
            $query->where('creado_en', '>=', $filtros->desde->startOfDay());
        }

        if ($filtros->hasta) {
            $query->where('creado_en', '<=', $filtros->hasta->endOfDay());
        }

        if ($filtros->campoId) {
            if ($esAdmin) {
                $query->whereHas('detalles', function ($q) use ($filtros) {
                    $q->where('campo_id', $filtros->campoId);
                });
            } else {
                $query->whereHas('detalles', function ($q) use ($filtros, $funcionario) {
                    $q->where('campo_id', $filtros->campoId)
                        ->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                            $aq->where('funcionario_id', $funcionario->id);
                        });
                });
            }
        }

        if ($filtros->funcionarioControlId) {
            $query->whereHas('detalles.campo.asignacionesFuncionario', function ($q) use ($filtros) {
                $q->where('funcionario_id', $filtros->funcionarioControlId);
            });
        }

        if ($filtros->buscar) {
            $termino = '%'.$this->escaparPatronLike($filtros->buscar).'%';

            $query->where(function ($q) use ($termino) {
                $q->whereRaw('codigo_seguimiento ILIKE ?', [$termino])
                    ->orWhereRaw('nombre_pagador ILIKE ?', [$termino])
                    ->orWhereRaw('telefono_pagador ILIKE ?', [$termino])
                    ->orWhereRaw('ci_nit_pagador ILIKE ?', [$termino]);
            });
        }

        return $query
            ->orderByDesc('creado_en')
            ->orderByDesc('id');
    }

    private function escaparPatronLike(string $valor): string
    {
        return addcslashes($valor, '\\%_');
    }
}
