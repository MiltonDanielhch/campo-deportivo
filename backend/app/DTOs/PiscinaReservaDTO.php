<?php

namespace App\DTOs;

class PiscinaReservaDTO
{
    public function __construct(
        public readonly string $fecha,
        public readonly string $turnoId,
        public readonly array $items, // [['perfil' => 'mayor_12', 'cantidad' => 3], ...]
        public readonly string $nombrePagador,
        public readonly string $telefonoPagador,
        public readonly string $ciNitPagador,
        public readonly string $ipCobrador,
    ) {
    }

    /**
     * Valida los items de la reserva.
     */
    public function validarItems(): void
    {
        if (empty($this->items)) {
            throw new \InvalidArgumentException('Debe haber al menos un perfil');
        }

        $perfilesValidos = ['mayor_12', 'menor_12', 'institucion'];

        foreach ($this->items as $item) {
            if (!isset($item['perfil']) || !in_array($item['perfil'], $perfilesValidos)) {
                throw new \InvalidArgumentException('Perfil inválido: ' . ($item['perfil'] ?? 'null'));
            }

            if (!isset($item['cantidad']) || $item['cantidad'] <= 0) {
                throw new \InvalidArgumentException('La cantidad debe ser mayor a 0');
            }
        }
    }
}
