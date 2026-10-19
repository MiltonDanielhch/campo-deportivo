<?php

namespace App\Jobs;

use App\Enums\EstadoPiscinaReserva;
use App\Models\PiscinaReserva;
use App\Services\AuditoriaService;
use App\Services\Sireb\SirebLiquidacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reintenta crear liquidación en SIREB si falló inicialmente.
 *
 * Delega la liquidación al helper central (SirebLiquidacionService). Este job
 * solo decide la política ante el fallo:
 *   - intento < MAX  -> re-dispatch con delay
 *   - intento == MAX -> rechazar reserva + liberar cupos + auditar
 *
 * El helper lanza cualquier excepción; acá se atrapa con \Throwable para no
 * dejar la reserva pendiente-forever si el fallo no es de SIREB (DB, shape
 * malformado, etc.).
 */
class ReintentarPiscinaReservaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_REINTENTOS = 5;
    public const RETRASO_SEGUNDOS = 60;

    public function __construct(
        public readonly string $reservaId,
        public readonly int $intento = 1,
    ) {
        $this->onQueue('sireb-reintentos');
    }

    public function handle(
        AuditoriaService $auditoria,
        SirebLiquidacionService $liquidaciones,
    ): void {
        $reserva = PiscinaReserva::find($this->reservaId);

        if (!$reserva || $reserva->estado !== EstadoPiscinaReserva::Pendiente) {
            return;
        }

        // Si ya tiene liquidación, no reintentar
        if ($reserva->liquidacion_id) {
            return;
        }

        try {
            // Construir items (dominio de piscina: tarifa ya guardada en el detalle)
            $items = [];
            foreach ($reserva->detalles as $detalle) {
                $items[] = [
                    'tarifa_id' => $detalle->tarifa_sireb_id,
                    'cantidad' => $detalle->cantidad,
                ];
            }

            // Delegar al helper central: cliente + liquidación + persistencia + auditoría
            $liquidaciones->crearYPersistir(
                reserva: $reserva,
                items: $items,
                idempotencyPrefix: 'sedede:piscina',
                tablaAuditoria: 'piscina_reservas',
            );

            Log::channel('sireb')->info('Liquidación creada exitosamente en SIREB (reintento)', [
                'reserva_id' => $reserva->id,
                'intento' => $this->intento,
            ]);

        } catch (\Throwable $e) {
            Log::channel('sireb')->error('Error al crear liquidación SIREB (reintento)', [
                'reserva_id' => $reserva->id,
                'intento' => $this->intento,
                'error' => $e->getMessage(),
            ]);

            // Reintentar si no excedió el máximo
            if ($this->intento < self::MAX_REINTENTOS) {
                self::dispatch($this->reservaId, $this->intento + 1)
                    ->delay(now()->addSeconds(self::RETRASO_SEGUNDOS));
            } else {
                // Rechazar reserva después de máximos reintentos
                $reserva->update([
                    'estado' => EstadoPiscinaReserva::Rechazada,
                    'motivo_rechazo' => 'error_cobro_inicial',
                ]);

                // Liberar cupos
                foreach ($reserva->detalles as $detalle) {
                    $perfilTurno = $detalle->turno->perfiles()
                        ->where('perfil', $detalle->perfil)
                        ->first();

                    if ($perfilTurno) {
                        $perfilTurno->decrement('reservado', $detalle->cantidad);
                    }
                }

                $auditoria->registrar(
                    'piscina_reservas',
                    $reserva->id,
                    'rechazar_por_error_cobro',
                    null,
                    ['estado' => 'pendiente'],
                    ['estado' => 'rechazada', 'motivo_rechazo' => 'error_cobro_inicial'],
                );
            }
        }
    }
}