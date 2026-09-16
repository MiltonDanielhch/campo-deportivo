<?php

namespace App\DTOs;

/**
 * Datos completos de una solicitud de reserva multi-franja.
 * El monto real y definitivo SIEMPRE lo calcula el backend.
 */
final class SolicitudReservaDTO
{
    /**
     * @param FranjaSolicitadaDTO[] $franjas
     */
    public function __construct(
        public readonly string $nombrePagador,
        public readonly string $telefonoPagador,
        public readonly ?string $ciNitPagador,
        public readonly array $franjas,
    ) {}

    /**
     * Construye el DTO desde el array validado por el FormRequest.
     */
    public static function desdeArray(array $data): self
    {
        return new self(
            nombrePagador: $data['nombre_pagador'],
            telefonoPagador: $data['telefono_pagador'],
            ciNitPagador: $data['ci_nit_pagador'] ?? null,
            franjas: array_map(
                fn (array $f) => new FranjaSolicitadaDTO(
                    campoId: $f['campo_id'],
                    fecha: $f['fecha'],
                    horaInicio: $f['hora_inicio'],
                    horaFin: $f['hora_fin'],
                ),
                $data['franjas'],
            ),
        );
    }
}
