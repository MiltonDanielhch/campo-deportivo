<?php

namespace App\Jobs;

use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Expira una solicitud pendiente al vencer su timer.
 *
 * Antes de expirar localmente intenta anular la liquidación en SIREB
 * (HU-047: SIREB solo permite anular si está pendiente y sin pago activo).
 *
 * Regla de conciliación:
 *  - Anulación OK            → expirar localmente.
 *  - LIQUIDACION_NO_ANULABLE → la liquidación YA tiene pago: NO expirar.
 *    Se deja pendiente y el polling confirmará cuando SIREB valide el pago.
 *  - Otro error (5xx, red)   → expirar localmente igual y loguear error.
 */
class ExpirarSolicitudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $solicitudId,
    ) {
    }

    public function handle(RecaudacionesApiClientInterface $client): void
    {
        $solicitud = SolicitudReserva::find($this->solicitudId);

        if (! $solicitud || $solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return;
        }

        // Si la liquidación ya tiene pago activo, no expiramos:
        // el polling se encargará de confirmar cuando SIREB valide.
        if (! empty($solicitud->liquidacion_id)
            && ! $this->intentarAnularEnSireb($solicitud, $client)) {
            return;
        }

        $solicitud->update([
            'estado' => EstadoSolicitudReserva::Expirada,
            'motivo_rechazo' => 'expirada_sin_pago',
        ]);

        AuditoriaService::registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'expirar_automaticamente',
            null,
            ['estado' => 'pendiente'],
            ['estado' => 'expirada', 'motivo_rechazo' => 'expirada_sin_pago'],
        );
    }

    /**
     * true  = se puede expirar localmente (anulación OK o error tolerable).
     * false = la liquidación ya tiene pago activo: NO expirar.
     */
    private function intentarAnularEnSireb(
        SolicitudReserva $solicitud,
        RecaudacionesApiClientInterface $client,
    ): bool {
        try {
            $client->anularLiquidacion(
                $solicitud->liquidacion_id,
                'solicitud_expirada_sin_pago',
            );

            Log::channel('sireb')->info('Liquidación anulada en SIREB por expiración', [
                'solicitud_id' => $solicitud->id,
                'liquidacion_id' => $solicitud->liquidacion_id,
            ]);

            return true;
        } catch (RecaudacionesApiException $e) {
            if (str_contains($e->getMessage(), 'LIQUIDACION_NO_ANULABLE')) {
                Log::channel('sireb')->warning(
                    'No se anula: la liquidación ya tiene pago activo. Se deja pendiente para confirmación por polling.',
                    [
                        'solicitud_id' => $solicitud->id,
                        'liquidacion_id' => $solicitud->liquidacion_id,
                    ],
                );

                return false;
            }

            Log::channel('sireb')->error(
                'Error al anular en SIREB; se expira localmente igual',
                [
                    'solicitud_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ],
            );

            return true;
        }
    }
}
