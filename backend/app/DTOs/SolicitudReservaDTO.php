<?php

namespace App\DTOs;

readonly class SolicitudReservaDTO
{
    /**
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @param  ServicioAdicionalDTO[]  $servicios
     */
    public function __construct(
        public string $nombrePagador,
        public string $telefonoPagador,
        public ?string $ciNitPagador,
        public array $franjas,
        public ?string $ipCobrador = null,
        public array $servicios = [],
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
                varianteId: $f['variante_id'] ?? null,
                // [Fase 6a] Propagar alumnos_ids desde el payload público.
                // Sin esto, validarAlumnosPertenencia ve [] y hace return
                // temprano: los alumnos asignados online se pierden en silencio.
                // El FormRequest ya validó la forma (uuid, exists); acá solo
                // se hilan al DTO para que el service los valide contra la
                // asociación del token.
                alumnosIds: $f['alumnos_ids'] ?? [],
            ))
            ->all();

        $servicios = collect($data['servicios'] ?? [])
            ->map(fn (array $s) => new ServicioAdicionalDTO(
                campoId: $s['campo_id'],
                varianteId: $s['variante_id'],
                cantidad: (int) ($s['cantidad'] ?? 1),
            ))
            ->all();

        return new self(
            nombrePagador: $data['nombre_pagador'],
            telefonoPagador: $data['telefono_pagador'],
            ciNitPagador: $data['ci_nit_pagador'] ?? null,
            franjas: $franjas,
            ipCobrador: $data['ip_cobrador'] ?? null,
            servicios: $servicios,
        );
    }
}
