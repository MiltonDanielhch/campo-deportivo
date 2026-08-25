<?php

namespace App\DTOs;

/**
 * Franja horaria que el ciudadano desea reservar.
 * Las fechas viajan como strings ISO 8601 en zona horaria America/La_Paz.
 */
final class FranjaSolicitadaDTO
{
    public function __construct(
        public readonly int $campoId,
        public readonly string $inicio,
        public readonly string $fin,
    ) {}

    public function toArray(): array
    {
        return [
            'campo_id' => $this->campoId,
            'inicio' => $this->inicio,
            'fin' => $this->fin,
        ];
    }
}
