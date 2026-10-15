<?php

namespace App\Jobs;

use App\Enums\EstadoPiscinaReserva;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ParametroSistema;
use App\Models\PiscinaReserva;
use App\Services\Piscina\PiscinaConfirmacionCobroService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Polling de estado de pago para reservas de piscina.
 */
class PollingPiscinaReservaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $reservaId
    ) {}

    public function handle(
        RecaudacionesApiClientInterface $client,
        PiscinaConfirmacionCobroService $confirmacion
    ): void {
        $reserva = PiscinaReserva::find($this->reservaId);

        if (!$reserva || $reserva->estado !== EstadoPiscinaReserva::Pendiente) {
            return;
        }

        if (Carbon::now()->greaterThan($reserva->expira_en)) {
            return;
        }

        $estado = $client->consultarEstado($reserva->referencia_recaudaciones);

        if ($estado && $estado->pagado) {
            $confirmacion->confirmar($reserva, $estado->montoConfirmado);
            return;
        }

        $intervalo = (int) ParametroSistema::where('clave', 'polling_intervalo_segundos')->value('valor');
        self::dispatch($this->reservaId)->delay(now()->addSeconds($intervalo ?: 20));
    }
}
