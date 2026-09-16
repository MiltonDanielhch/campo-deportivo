<?php

namespace App\Jobs;

use App\Enums\EstadoSolicitudReserva;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpirarSolicitudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $solicitudId
    ) {}

    public function handle(): void
    {
        $solicitud = SolicitudReserva::find($this->solicitudId);

        if (! $solicitud) {
            return;
        }

        if ($solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return;
        }

        $solicitud->update(['estado' => EstadoSolicitudReserva::Expirada]);

        AuditoriaService::registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'expirar_automaticamente',
            null,
            ['estado' => 'pendiente'],
            ['estado' => 'expirada']
        );
    }
}
