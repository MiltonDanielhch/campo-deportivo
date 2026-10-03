<?php

namespace App\DTOs;

use App\Enums\EstadoSolicitudReserva;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SolicitudFiltrosDTO
{
    public function __construct(
        public ?string $estado = null,
        public ?CarbonImmutable $desde = null,
        public ?CarbonImmutable $hasta = null,
        public ?string $campoId = null,
        public ?string $funcionarioControlId = null,
        public ?string $buscar = null,
        public int $page = 1,
        public int $perPage = 15,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $estado = $request->query('estado');

        if ($estado !== null && $estado !== '') {
            $valoresValidos = array_column(EstadoSolicitudReserva::cases(), 'value');

            if (! in_array($estado, $valoresValidos, true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Estado inválido. Valores permitidos: '.implode(', ', $valoresValidos).'.',
                ]);
            }
        } else {
            $estado = null;
        }

        $desde = self::parseFecha($request->query('desde'), 'desde');
        $hasta = self::parseFecha($request->query('hasta'), 'hasta');

        if ($desde && $hasta && $desde->greaterThan($hasta)) {
            throw ValidationException::withMessages([
                'desde' => 'La fecha "desde" no puede ser posterior a "hasta".',
            ]);
        }

        $campoId = self::parseUuid($request->query('campo_id'), 'campo_id');
        $funcionarioControlId = self::parseUuid($request->query('funcionario_control_id'), 'funcionario_control_id');

        $buscar = $request->query('buscar') ?? $request->query('codigo');
        $buscar = is_string($buscar) ? trim($buscar) : null;

        if ($buscar === '') {
            $buscar = null;
        }

        $page = max(1, $request->integer('page', 1));

        $perPage = $request->integer('per_page', 15);

        if ($perPage < 1) {
            $perPage = 15;
        }

        $perPage = min(100, $perPage);

        return new self(
            estado: $estado,
            desde: $desde,
            hasta: $hasta,
            campoId: $campoId,
            funcionarioControlId: $funcionarioControlId,
            buscar: $buscar,
            page: $page,
            perPage: $perPage,
        );
    }

    private static function parseFecha(mixed $value, string $campo): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $campo => 'Fecha inválida. Use formato YYYY-MM-DD.',
            ]);
        }

        $fecha = CarbonImmutable::createFromFormat('Y-m-d', $value);

        if ($fecha === false) {
            throw ValidationException::withMessages([
                $campo => 'Fecha inválida. Use formato YYYY-MM-DD.',
            ]);
        }

        return $fecha;
    }

    private static function parseUuid(mixed $value, string $campo): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! Str::isUuid($value)) {
            throw ValidationException::withMessages([
                $campo => 'Identificador inválido.',
            ]);
        }

        return $value;
    }
}
