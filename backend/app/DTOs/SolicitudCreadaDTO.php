<?php

namespace App\DTOs;

use App\Models\SolicitudReserva;

/**
 * Resultado de SolicitudReservaService::crear(): la solicitud persistida
 * más la respuesta de cobro del Core (para armar el bloque 'cobro' del 201).
 */
final class SolicitudCreadaDTO
{
    public function __construct(
        public readonly SolicitudReserva $solicitud,
        public readonly ?RespuestaCobroDTO $cobro,
    ) {}
}
