<?php

namespace App\DTOs;

use App\Enums\ModalidadReservaCampo;

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
        public readonly ?string $provincia = null,
        public readonly ?string $municipio = null,
        public readonly float $latitud,
        public readonly float $longitud,
        public readonly array $horarios,
        // [B94] el funcionario elige la modalidad en el alta; default franja_horaria
        // (coincide con el default de la migración, así que un caller que no la manda
        // —tests, sync— no pisa la columna con null, que CampoController:64 trata como
        // "visible al público"). Va AL FINAL del constructor: los parámetros con default
        // no pueden ir antes de los obligatorios (latitud/longitud/horarios).
        public readonly ModalidadReservaCampo $modalidad_reserva = ModalidadReservaCampo::FranjaHoraria,
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
            provincia: $data['provincia'] ?? null,
            municipio: $data['municipio'] ?? null,
            latitud: (float) $data['latitud'],
            longitud: (float) $data['longitud'],
            horarios: $horarios,
            // [B94] tryFrom pa' no depender de que el cast acepte string crudo; si el
            // request no mandó modalidad (nullable) o mandó un valor fuera del enum,
            // cae a FranjaHoraria (= default DB), nunca null.
            modalidad_reserva: ModalidadReservaCampo::tryFrom((string) ($data['modalidad_reserva'] ?? ''))
                ?? ModalidadReservaCampo::FranjaHoraria,
        );
    }
}
