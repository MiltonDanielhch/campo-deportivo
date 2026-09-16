<?php

namespace App\DTOs;

/**
 * Franja horaria que el ciudadano desea reservar.
 * Coincide con las columnas de solicitud_reserva_detalle:
 * fecha_reserva (date) + hora_inicio/hora_fin (time).
 */
final class FranjaSolicitadaDTO
{
    public function __construct(
        public readonly string $campoId,     // UUID del campo
        public readonly string $fecha,       // 'YYYY-MM-DD'
        public readonly string $horaInicio,  // 'HH:MM' o 'HH:MM:SS'
        public readonly string $horaFin,     // 'HH:MM' o 'HH:MM:SS'
    ) {}

    public function toArray(): array
    {
        return [
            'campo_id' => $this->campoId,
            'fecha' => $this->fecha,
            'hora_inicio' => $this->horaInicio,
            'hora_fin' => $this->horaFin,
        ];
    }
}
