<?php

namespace App\Events;

use App\Models\SolicitudReserva;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado cuando una solicitud de reserva se confirma exitosamente.
 * Preparación para Módulo 10 (notificaciones email/SMS).
 */
class SolicitudConfirmada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SolicitudReserva $solicitud,
    ) {
    }
}
