<?php

namespace App\Jobs;

use App\Enums\EstadoSolicitudReserva;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ParametroSistema;
use App\Models\SolicitudReserva;
use App\Services\ConfirmacionCobroService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class PollingSolicitudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $solicitudId
    ) {}

    public function handle(
        RecaudacionesApiClientInterface $client,
        ConfirmacionCobroService $confirmacion
    ): void {
        $solicitud = SolicitudReserva::find($this->solicitudId);

        if (! $solicitud || $solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return;
        }

        if (Carbon::now()->greaterThan($solicitud->expira_en)) {
            return;
        }

        $estado = $client->consultarEstado($solicitud->referencia_recaudaciones);

        if ($estado && $estado->pagado) {
            $confirmacion->confirmar($solicitud, $estado->montoConfirmado);
            return;
        }

        $intervalo = (int) ParametroSistema::where('clave', 'polling_intervalo_segundos')->value('valor');
        self::dispatch($this->solicitudId)->delay(now()->addSeconds($intervalo ?: 20));
    }
}
