<?php

namespace App\DTOs;

/**
 * Servicio adicional solicitado junto a una reserva.
 *
 * P8.3B: limpieza, energía, escenario, etc.
 * No ocupa franja horaria; solo se liquida en SIREB.
 */
final class ServicioAdicionalDTO
{
    public function __construct(
        public readonly string $campoId,
        public readonly string $varianteId,
        public readonly int $cantidad = 1,
    ) {}

    public function toArray(): array
    {
        return [
            'campo_id' => $this->campoId,
            'variante_id' => $this->varianteId,
            'cantidad' => $this->cantidad,
        ];
    }
}
