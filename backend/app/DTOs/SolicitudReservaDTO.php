<?php

namespace App\DTOs;

readonly class SolicitudReservaDTO
{
    /**
     * @param  FranjaSolicitadaDTO[]  $franjas
     */
    public function __construct(
        public string $nombrePagador,
        public string $telefonoPagador,
        public ?string $ciNitPagador,
        public array $franjas,
        public ?string $ipCobrador = null,  // ← NUEVO
    ) {
    }

    public static function desdeArray(array $data): self
    {
        $franjas = collect($data['franjas'] ?? [])
            ->map(fn (array $f) => new FranjaSolicitadaDTO(
                campoId: $f['campo_id'],
                fecha: $f['fecha'],
                horaInicio: $f['hora_inicio'],
                horaFin: $f['hora_fin'],
            ))
            ->all();

        return new self(
            nombrePagador: $data['nombre_pagador'],
            telefonoPagador: $data['telefono_pagador'],
            ciNitPagador: $data['ci_nit_pagador'] ?? null,
            franjas: $franjas,
            ipCobrador: $data['ip_cobrador'] ?? null,  // ← NUEVO
        );
    }
}
