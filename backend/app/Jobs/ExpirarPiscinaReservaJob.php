<?php

namespace App\Jobs;

use App\Enums\EstadoPiscinaReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\PiscinaReserva;
use App\Services\AuditoriaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Expira una reserva de piscina pendiente al vencer su timer.
 */
class ExpirarPiscinaReservaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $reservaId,
    ) {
    }

    public function handle(RecaudacionesApiClientInterface $client, AuditoriaService $auditoria): void
    {
        $reserva = PiscinaReserva::find($this->reservaId);

        if (!$reserva || $reserva->estado !== EstadoPiscinaReserva::Pendiente) {
            return;
        }

        // Intentar anular en SIREB (si tiene liquidacion_id)
        $sePuedeExpirar = true;
        if (!empty($reserva->liquidacion_id)) {
            $sePuedeExpirar = $this->intentarAnularEnSireb($reserva, $client);
        }

        // Marcar como expirada solo si SIREB lo permite
        if ($sePuedeExpirar) {
            $reserva->update([
                'estado' => EstadoPiscinaReserva::Expirada,
                'motivo_rechazo' => 'expirada_sin_pago',
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
                'expirar_automaticamente',
                null,
                ['estado' => 'pendiente'],
                ['estado' => 'expirada', 'motivo_rechazo' => 'expirada_sin_pago'],
            );
        }
    }

    private function intentarAnularEnSireb(
        PiscinaReserva $reserva,
        RecaudacionesApiClientInterface $client,
    ): bool {
        try {
            $client->anularLiquidacion(
                $reserva->liquidacion_id,
                'reserva_piscina_expirada_sin_pago',
            );

            Log::channel('sireb')->info('Liquidación anulada en SIREB por expiración piscina', [
                'reserva_id' => $reserva->id,
                'liquidacion_id' => $reserva->liquidacion_id,
            ]);

            return true;
        } catch (RecaudacionesApiException $e) {
            if (str_contains($e->getMessage(), 'LIQUIDACION_NO_ANULABLE')) {
                Log::channel('sireb')->warning(
                    'No se anula: la liquidación ya tiene pago activo. Se deja pendiente para confirmación por polling.',
                    [
                        'reserva_id' => $reserva->id,
                        'liquidacion_id' => $reserva->liquidacion_id,
                    ],
                );

                return false;
            }

            Log::channel('sireb')->error(
                'Error al anular en SIREB; se expira localmente igual',
                [
                    'reserva_id' => $reserva->id,
                    'error' => $e->getMessage(),
                ],
            );

            return true;
        }
    }
}
