<?php

namespace App\Services;

use App\DTOs\CrearCampoDTO;
use App\Models\CampoDeportivo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CampoDeportivoService
{
    /**
     * Lista campos deportivos con filtros opcionales.
     */
    public function listar(
        ?string $tipoCampoId = null,
        ?string $estado = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = CampoDeportivo::with('tipoCampo');

        if ($tipoCampoId) {
            $query->where('tipo_campo_id', $tipoCampoId);
        }

        if ($estado) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('nombre')->paginate($perPage);
    }

    /**
     * Obtiene el detalle de un campo con sus horarios.
     */
    public function obtenerDetalle(CampoDeportivo $campo): CampoDeportivo
    {
        return $campo->load(['tipoCampo', 'horariosAtencion']);
    }

    /**
     * Crea un campo con sus horarios en una transacción atómica.
     * Si algún horario falla, el campo tampoco se crea.
     */
    public function crear(CrearCampoDTO $dto): CampoDeportivo
    {
        return DB::transaction(function () use ($dto): CampoDeportivo {
            // 1. Crear el campo
            $campo = CampoDeportivo::create([
                'tipo_campo_id' => $dto->tipo_campo_id,
                'codigo' => $dto->codigo,
                'nombre' => $dto->nombre,
                'direccion' => $dto->direccion,
                'latitud' => $dto->latitud,
                'longitud' => $dto->longitud,
                'estado' => 'activo',
            ]);

            // 2. Crear los horarios (si uno falla, la transacción hace rollback)
            foreach ($dto->horarios as $horario) {
                $campo->horariosAtencion()->create([
                    'dia_semana' => $horario->dia_semana,
                    'hora_apertura' => $horario->hora_apertura,
                    'hora_cierre' => $horario->hora_cierre,
                ]);
            }

            return $campo->load('horariosAtencion');
        });
    }

    /**
     * Actualiza los datos generales del campo (NO el estado).
     */
    public function actualizar(CampoDeportivo $campo, array $data): CampoDeportivo
    {
        $campo->update([
            'tipo_campo_id' => $data['tipo_campo_id'] ?? $campo->tipo_campo_id,
            'codigo' => $data['codigo'] ?? $campo->codigo,
            'nombre' => $data['nombre'] ?? $campo->nombre,
            'direccion' => $data['direccion'] ?? $campo->direccion,
            'latitud' => $data['latitud'] ?? $campo->latitud,
            'longitud' => $data['longitud'] ?? $campo->longitud,
        ]);

        return $campo->fresh()->load(['tipoCampo', 'horariosAtencion']);
    }

    /**
     * Cambia el estado del campo.
     * PLACEHOLDER: en la Fase 2.3 se conectará con AuditoriaService.
     */
    public function cambiarEstado(CampoDeportivo $campo, string $nuevoEstado): CampoDeportivo
    {
        $estadoAnterior = $campo->estado;

        $campo->update(['estado' => $nuevoEstado]);

        // TODO Fase 2.3: AuditoriaService::registrar(
        //     'campos_deportivos',
        //     $campo->id,
        //     'cambiar_estado',
        //     $usuarioId,
        //     ['estado' => $estadoAnterior],
        //     ['estado' => $nuevoEstado]
        // );

        return $campo->fresh();
    }
}
