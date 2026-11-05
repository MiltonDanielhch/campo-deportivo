<?php

namespace App\DTOs;

/**
 * Franja horaria que el ciudadano/funcionario desea reservar.
 *
 * Coincide con las columnas de solicitud_reserva_detalle:
 * fecha_reserva (date) + hora_inicio/hora_fin (time).
 *
 * P8.2B: variante_id es opcional.
 * - Null: comportamiento legacy, campo con servicio SIREB base.
 * - Con UUID: reserva una variante arancelaria del campo físico.
 */
final class FranjaSolicitadaDTO
{
    public function __construct(
        public readonly string $campoId,
        public readonly string $fecha,
        public readonly string $horaInicio,
        public readonly string $horaFin,
        public readonly ?string $varianteId = null,
        /** [Fase 3] uuids de alumnos de la asociacion asignados a esta franja. */
        public readonly array $alumnosIds = [],
    ) {}

    public function toArray(): array
    {
        return [
            'campo_id' => $this->campoId,
            'fecha' => $this->fecha,
            'hora_inicio' => $this->horaInicio,
            'hora_fin' => $this->horaFin,
            'variante_id' => $this->varianteId,
            'alumnos_ids' => $this->alumnosIds,
        ];
    }
}
