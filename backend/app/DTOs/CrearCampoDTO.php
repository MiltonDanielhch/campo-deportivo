<?php

namespace App\DTOs;

class CrearCampoDTO
{
    /**
     * @param HorarioAtencionDTO[] $horarios
     */
    public function __construct(
        public readonly string $tipo_campo_id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $direccion,
        public readonly float $latitud,
        public readonly float $longitud,
        public readonly array $horarios,
    ) {}

    public static function fromArray(array $data): self
    {
        $horarios = array_map(
            fn (array $h) => new HorarioAtencionDTO(
                dia_semana: (int) $h['dia_semana'],
                hora_apertura: $h['hora_apertura'],
                hora_cierre: $h['hora_cierre'],
            ),
            $data['horarios'] ?? [],
        );

        return new self(
            tipo_campo_id: $data['tipo_campo_id'],
            codigo: $data['codigo'],
            nombre: $data['nombre'],
            direccion: $data['direccion'],
            latitud: (float) $data['latitud'],
            longitud: (float) $data['longitud'],
            horarios: $horarios,
        );
    }
}
