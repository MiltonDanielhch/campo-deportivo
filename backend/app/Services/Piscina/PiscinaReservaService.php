<?php

namespace App\Services\Piscina;

use App\DTOs\PiscinaReservaDTO;
use App\Enums\EstadoPiscinaReserva;
use App\Enums\ModalidadReservaCampo;
use App\Exceptions\FranjaNoDisponibleException;
use App\Jobs\ExpirarPiscinaReservaJob;
use App\Jobs\PollingPiscinaReservaJob;
use App\Jobs\ReintentarPiscinaReservaJob;
use App\Models\ParametroSistema;
use App\Models\PiscinaReserva;
use App\Models\PiscinaReservaDetalle;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Services\AuditoriaService;
use App\Services\Sireb\SirebLiquidacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * Servicio de creación/cancelación de reservas de piscina.
 *
 * Flujo crear:
 * 1. Validar campo, turno, items
 * 2. Validar disponibilidad (cupo) con lockForUpdate
 * 3. Resolver tarifas desde espejo SIREB
 * 4. Calcular monto
 * 5. Transacción: crear reserva + detalles + reservar cupo
 * 6. Crear liquidación SIREB (delegada al helper central)
 * 7. Dispatch jobs (expiración, polling, reintentos)
 *
 * Flujo cancelar (Fix3):
 * 1. Validar estado pendiente
 * 2. Transacción: liberar cupo con lockForUpdate + marcar cancelada
 * 3. Auditoría
 *
 * Política ante fallo de SIREB:
 *   - público    -> despacha ReintentarPiscinaReservaJob y continúa
 *   - ventanilla -> rechaza inline, libera cupo y lanza (controller 422)
 */
class PiscinaReservaService
{
    private const VENTANA_DIAS = 60;
    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const TIMEZONE_BOLIVIA = 'America/La_Paz';

    public function __construct(
        private PiscinaTarifaResolver $tarifaResolver,
        private AuditoriaService $auditoria,
        private SirebLiquidacionService $liquidaciones,
    ) {}

    public function crear(PiscinaReservaDTO $datos, bool $esAdmin = false): array
    {
        $datos->validarItems();

        $turno = PiscinaTurno::with('campo')->find($datos->turnoId);
        if (! $turno) {
            throw new \InvalidArgumentException('Turno no encontrado');
        }

        $campo = $turno->campo;
        if (! $campo) {
            throw new \InvalidArgumentException('Campo no encontrado');
        }

        if ($campo->modalidad_reserva?->value !== ModalidadReservaCampo::TurnoPorPerfil->value) {
            throw new \InvalidArgumentException('Este campo no acepta reservas de piscina');
        }

        if ($turno->estado !== 'activo') {
            throw new \InvalidArgumentException('El turno no está activo');
        }

                // [FIX piscina-tz-creacion] Carbon::parse($turno->fecha) SIN tz caía en
        // APP_TIMEZONE (UTC en Docker): medianoche-UTC del turno vs medianoche-La_Paz
        // de $hoy → lt($hoy) = true → rechazaba un turno legítimo de HOY como
        // "pasado". Es el FIX #2 tz de cancha aplicado a medias acá (now() sí tenía
        // tz, parse() no). Parseo en America/La_Paz + startOfDay explícito para que
        // ambas partes de la comparación estén en la misma tz y al mismo instante.
        $fechaTurno = Carbon::parse($turno->fecha, self::TIMEZONE_BOLIVIA)->startOfDay();
        $hoy = Carbon::now(self::TIMEZONE_BOLIVIA)->startOfDay();

        if ($fechaTurno->lt($hoy)) {
            throw new \InvalidArgumentException('No se pueden reservar turnos pasados');
        }

        if ($fechaTurno->gt($hoy->copy()->addDays(self::VENTANA_DIAS))) {
            throw new \InvalidArgumentException(
                'La fecha excede el máximo de anticipación de '.self::VENTANA_DIAS.' días'
            );
        }

        // [FIX piscina-pasada-hora] gate de HORA espejo de validarFranjaFutura (cancha).
        // El gate de fecha de arriba solo rechaza AYER; un turno de HOY 10:00-12:00
        // confirmado a las 15:00 pasaba (fecha = hoy → lt($hoy) = false). Comparo el
        // datetime (fecha + hora_inicio) en America/La_Paz contra ahoraBolivia().
        // Cierra el bypass por API y la carrera abrir(11:59)-confirmar(12:01) que el
        // fix de disponibilidad (que solo cerró la GRILLA) no cubre. Regla idéntica a
        // cancha: no se reserva un turno que ya comenzó (incluso este segundo).
        $inicioTurno = Carbon::parse(
            "{$turno->fecha} {$turno->hora_inicio}",
            self::TIMEZONE_BOLIVIA,
        );
        $ahoraBolivia = Carbon::now(self::TIMEZONE_BOLIVIA);

        if ($inicioTurno->lessThanOrEqualTo($ahoraBolivia)) {
            throw new \InvalidArgumentException(
                "El turno {$turno->hora_inicio}-{$turno->hora_fin} ya comenzó. Elegí un turno futuro."
            );
        }

        $itemsValidados = $this->validarItemsYCalcularMonto($turno, $datos->items);
        $montoTotal = collect($itemsValidados)->sum('subtotal');

        $expiracionMinutos = $this->minutosExpiracion();

        try {
            $reserva = DB::transaction(function () use ($datos, $turno, $itemsValidados, $montoTotal, $expiracionMinutos) {
                $reserva = PiscinaReserva::create([
                    'codigo_seguimiento' => $this->generarCodigoSeguimiento(),
                    'campo_id'           => $turno->campo_id,
                    'estado'             => EstadoPiscinaReserva::Pendiente,
                    'monto_total'        => $montoTotal,
                    'nombre_pagador'     => $this->normalizarPagador($datos->nombrePagador),   // FIX B65
                    'telefono_pagador'   => $datos->telefonoPagador,
                    'ci_nit_pagador'     => $datos->ciNitPagador,
                    'ip_cobrador'        => $datos->ipCobrador,
                    'expira_en'          => now()->addMinutes($expiracionMinutos),
                ]);

                foreach ($itemsValidados as $item) {
                    $perfilTurno = PiscinaTurnoPerfil::where('turno_id', $turno->id)
                        ->where('perfil', $item['perfil'])
                        ->lockForUpdate()
                        ->first();

                    if (! $perfilTurno) {
                        throw new \InvalidArgumentException('Perfil no disponible en este turno');
                    }

                    if ($item['cantidad'] > $perfilTurno->disponible) {
                        throw new FranjaNoDisponibleException(
                            "No hay cupo suficiente para {$item['perfil']}. "
                            ."Disponible: {$perfilTurno->disponible}, solicitado: {$item['cantidad']}"
                        );
                    }

                    $perfilTurno->increment('reservado', $item['cantidad']);

                    PiscinaReservaDetalle::create([
                        'reserva_id'        => $reserva->id,
                        'turno_id'          => $turno->id,
                        'perfil'            => $item['perfil'],
                        'cantidad'          => $item['cantidad'],
                        'precio_unitario'   => $item['precio_unitario'],
                        'tarifa_sireb_id'   => $item['tarifa_sireb_id'],
                        'servicio_sireb_id' => $item['servicio_sireb_id'],
                        'etiqueta_sireb'    => $item['etiqueta_sireb'],
                        'hora_inicio'       => $turno->hora_inicio,
                        'hora_fin'          => $turno->hora_fin,
                        'fecha'             => $turno->fecha,
                    ]);
                }

                $this->auditoria->registrar(
                    'piscina_reservas',
                    $reserva->id,
                    'crear_reserva',
                    null,
                    null,
                    [
                        'codigo_seguimiento' => $reserva->codigo_seguimiento,
                        'monto_total'        => $montoTotal,
                        'turno_id'           => $turno->id,
                        'items'              => $itemsValidados,
                    ]
                );

                return $reserva;
            });

            try {
                $this->crearLiquidacionEnSireb($reserva);
            } catch (\Throwable $e) {
                if ($esAdmin) {
                    $this->rechazarPorFallaLiquidacion($reserva, $e);
                    throw $e;
                }

                Log::channel('sireb')->error('Error al crear liquidación SIREB en primer intento', [
                    'reserva_id' => $reserva->id,
                    'error'      => $e->getMessage(),
                ]);

                ReintentarPiscinaReservaJob::dispatch($reserva->id);
            }

            ExpirarPiscinaReservaJob::dispatch($reserva->id)->delay($reserva->expira_en);
            PollingPiscinaReservaJob::dispatch($reserva->id);

            $reserva->refresh();

            return [
                'reserva_id'              => $reserva->id,
                'codigo_seguimiento'      => $reserva->codigo_seguimiento,
                'monto_total'             => (float) $reserva->monto_total,
                'expira_en'               => $reserva->expira_en->toIso8601String(),
                'referencia_recaudaciones' => $reserva->referencia_recaudaciones,
                'qr_string'               => $reserva->datos_cobro_pendiente['qr_string'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::channel('sireb')->error('Error al crear reserva de piscina', [
                'error' => $e->getMessage(),
                'datos' => $datos,
            ]);
            throw $e;
        }
    }

    /**
     * [FIX Fix3] Cancela una reserva pendiente con lock + validación de cupo.
     *
     * Antes esto vivía en PiscinaReservaController::cancelar() sin lockForUpdate
     * y sin validar reservado >= cantidad, lo que permitía decrementos dobles en
     * cancelaciones concurrentes (reservado podía quedar negativo = cupo fantasma).
     *
     * Ahora es transaccional y seguro:
     * 1. Valida estado Pendiente (fuera de transacción, fail-fast).
     * 2. Transacción: lockForUpdate en cada perfilTurno, decrementa solo si
     *    reservado >= cantidad, marca cancelada con motivo, auditoría.
     */
    public function cancelar(PiscinaReserva $reserva, string $motivo, ?int $funcionarioId = null): void
    {
        if ($reserva->estado !== EstadoPiscinaReserva::Pendiente) {
            throw new \DomainException('Solo se pueden cancelar reservas pendientes');
        }

        DB::transaction(function () use ($reserva, $motivo, $funcionarioId) {
            $this->liberarCupos($reserva);

            $reserva->update([
                'estado'         => EstadoPiscinaReserva::Cancelada,
                'motivo_rechazo' => $motivo,
            ]);

            $this->auditoria->registrar(
                'piscina_reservas',
                $reserva->id,
                'cancelar_reserva',
                $funcionarioId,
                ['motivo' => $motivo],
                ['codigo_seguimiento' => $reserva->codigo_seguimiento]
            );
        });
    }

    /**
     * Libera cupos con lockForUpdate, garantizando que reservado no quede negativo.
     * Usado por cancelar() y por rechazarPorFallaLiquidacion().
     */
    private function liberarCupos(PiscinaReserva $reserva): void
    {
        foreach ($reserva->detalles as $detalle) {
            $perfilTurno = PiscinaTurnoPerfil::where('turno_id', $detalle->turno_id)
                ->where('perfil', $detalle->perfil)
                ->lockForUpdate()
                ->first();

            if (! $perfilTurno) {
                continue;
            }

            // Nunca permitir reservado negativo (protege contra doble decremento).
            $aDecrementar = min($detalle->cantidad, $perfilTurno->reservado);
            if ($aDecrementar > 0) {
                $perfilTurno->decrement('reservado', $aDecrementar);
            }
        }
    }

    private function validarItemsYCalcularMonto(PiscinaTurno $turno, array $items): array
    {
        $itemsValidados = [];

        foreach ($items as $item) {
            $perfil   = $item['perfil'];
            $cantidad = $item['cantidad'];

            $tarifa = $this->tarifaResolver->resolver(
                $turno->campo_id,
                $perfil,
                $turno->hora_inicio,
            );

            if (! $tarifa) {
                throw new \InvalidArgumentException("No hay tarifa vigente para el perfil {$perfil}");
            }

            $itemsValidados[] = [
                'perfil'            => $perfil,
                'cantidad'          => $cantidad,
                'precio_unitario'   => (float) $tarifa->precio_por_hora,
                'tarifa_sireb_id'   => $tarifa->tarifa_sireb_id,
                'servicio_sireb_id' => $tarifa->servicio_sireb_id,
                'etiqueta_sireb'    => $tarifa->etiqueta_sireb,
                'subtotal'          => (float) $tarifa->precio_por_hora * $cantidad,
            ];
        }

        return $itemsValidados;
    }

    private function crearLiquidacionEnSireb(PiscinaReserva $reserva): void
    {
        $items = [];

        foreach ($reserva->detalles as $detalle) {
            $items[] = [
                'tarifa_id' => $detalle->tarifa_sireb_id,
                'cantidad'  => $detalle->cantidad,
            ];
        }

        $this->liquidaciones->crearYPersistir(
            reserva: $reserva,
            items: $items,
            idempotencyPrefix: 'sedede:piscina',
            tablaAuditoria: 'piscina_reservas',
        );
    }

    private function generarCodigoSeguimiento(): string
    {
        do {
            $aleatorio = '';
            for ($i = 0; $i < 6; $i++) {
                $aleatorio .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
            $codigo = 'PIS-'.now()->format('Ymd').'-'.$aleatorio;
        } while (PiscinaReserva::where('codigo_seguimiento', $codigo)->exists());

        return $codigo;
    }

    private function minutosExpiracion(): int
    {
        return (int) ParametroSistema::where('clave', 'vigencia_minutos_solicitud')->value('valor') ?: 20;
    }

    /**
     * [FIX B65] Normaliza nombre del pagador: trim + title case, para que el
     * listado admin no muestre "MILTON DANIEL" (cancha) vs "daniel hipamo"
     * (piscina) con casing divergente.
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

    private function rechazarPorFallaLiquidacion(PiscinaReserva $reserva, \Throwable $e): void
    {
        DB::transaction(function () use ($reserva, $e) {
            $this->liberarCupos($reserva);   // reusa helper con lock + validación

            $reserva->update([
                'estado'         => EstadoPiscinaReserva::Rechazada,
                'motivo_rechazo' => 'cobro_ventanilla_fallo',
            ]);

            $this->auditoria->registrar(
                'piscina_reservas',
                $reserva->id,
                'rechazada_liquidacion_ventanilla',
                null,
                ['motivo' => 'cobro_ventanilla_fallo'],
                ['error' => $e->getMessage()]
            );
        });
    }

    /**
     * Genera filas CSV aplanadas: una fila por detalle (turno) de cada reserva.
     */
    public function exportarCsv(Request $request): \Generator
    {
        $query = $this->aplicarFiltrosParaExport(
            PiscinaReserva::with(['detalles', 'campo']),
            $request
        );

        yield [
            'codigo_seguimiento',
            'estado',
            'nombre_pagador',
            'telefono_pagador',
            'ci_nit_pagador',
            'campo_nombre',
            'fecha',
            'hora_inicio',
            'hora_fin',
            'perfil',
            'cantidad',
            'precio_unitario',
            'subtotal',
            'monto_total',
            'monto_confirmado',
            'referencia_recaudaciones',
            'creado_en',
            'expira_en',
        ];

        foreach ($query->lazy(500) as $reserva) {
            foreach ($reserva->detalles as $detalle) {
                $cantidad = (int) ($detalle->cantidad ?? 1);
                $precioUnitario = (float) ($detalle->precio_unitario ?? 0);
                $subtotal = round($precioUnitario * $cantidad, 2);

                yield [
                    (string) $reserva->codigo_seguimiento,
                    $reserva->estado->value,
                    (string) $reserva->nombre_pagador,
                    (string) ($reserva->telefono_pagador ?? ''),
                    (string) ($reserva->ci_nit_pagador ?? ''),
                    (string) ($reserva->campo?->nombre ?? ''),
                    (string) ($detalle->fecha ?? ''),
                    (string) substr((string) ($detalle->hora_inicio ?? ''), 0, 5),
                    (string) substr((string) ($detalle->hora_fin ?? ''), 0, 5),
                    (string) ($detalle->perfil ?? ''),
                    (string) $cantidad,
                    number_format($precioUnitario, 2, '.', ''),
                    number_format($subtotal, 2, '.', ''),
                    number_format((float) $reserva->monto_total, 2, '.', ''),
                    $reserva->monto_confirmado !== null
                        ? number_format((float) $reserva->monto_confirmado, 2, '.', '')
                        : '',
                    (string) ($reserva->referencia_recaudaciones ?? ''),
                    (string) ($reserva->created_at?->toIso8601String() ?? ''),
                    (string) ($reserva->expira_en?->toIso8601String() ?? ''),
                ];
            }
        }
    }

    /**
     * Replica aplicarFiltros del controller pero para queries de exportación.
     */
    private function aplicarFiltrosParaExport($query, Request $request)
    {
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('campo_id')) {
            $query->where('campo_id', $request->input('campo_id'));
        }
        if ($request->filled('fecha_desde')) {
            $query->where('created_at', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('created_at', '<=', $request->input('fecha_hasta').' 23:59:59');
        }
        if ($request->filled('buscar')) {
            $termino = $request->input('buscar');
            $query->where(function ($q) use ($termino) {
                $q->where('codigo_seguimiento', 'like', "%{$termino}%")
                    ->orWhere('nombre_pagador', 'like', "%{$termino}%")
                    ->orWhere('ci_nit_pagador', 'like', "%{$termino}%")
                    ->orWhere('telefono_pagador', 'like', "%{$termino}%")
                    ->orWhere('referencia_recaudaciones', 'like', "%{$termino}%");
            });
        }
        return $query->orderByDesc('created_at');
    }
}
