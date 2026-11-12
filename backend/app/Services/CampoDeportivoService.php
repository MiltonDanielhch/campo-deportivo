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
        // [visibilidad] eager-load de variantes ACTIVAS pa' el chip de
        // config de precios del listado. Filtramos activo=true igual que el
        // presenter (una variante inactiva no afecta a quién ve qué).
        $query = CampoDeportivo::with([
            'tipoCampo',
            'variantes' => fn ($q) => $q->where('activo', true)->orderBy('orden'),
        ]);

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
                // ⟦FIX ubicación⟧ el Request valida provincia/municipio pero el
                // service los descartaba → nunca se guardaban. property_exists
                // evita romper si CrearCampoDTO aún no los declara; en cuanto
                // los declare, se persisten solos.
                'provincia' => property_exists($dto, 'provincia') ? ($dto->provincia ?: null) : null,
                'municipio' => property_exists($dto, 'municipio') ? ($dto->municipio ?: null) : null,
                'latitud' => $dto->latitud,
                'longitud' => $dto->longitud,
                // [B94] instancia enum del DTO; el cast del model la serializa. Nunca null.
                'modalidad_reserva' => $dto->modalidad_reserva,
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
            // ⟦FIX ubicación⟧ igual que crear(): persistir provincia/municipio
            // si el payload los trae, sin borrar los ya guardados si no.
            'provincia' => array_key_exists('provincia', $data) ? ($data['provincia'] ?: null) : $campo->provincia,
            'municipio' => array_key_exists('municipio', $data) ? ($data['municipio'] ?: null) : $campo->municipio,
            'latitud' => $data['latitud'] ?? $campo->latitud,
            'longitud' => $data['longitud'] ?? $campo->longitud,
            // [B94] actualizar recibe array crudo (no DTO); si el 862 manda modalidad
            // (string válido, Rule::in lo garantiza) entra y el cast la convierte; si no
            // la manda, conserva la del campo (no la pisa con null).
            'modalidad_reserva' => $data['modalidad_reserva'] ?? $campo->modalidad_reserva,
        ]);

        return $campo->fresh()->load(['tipoCampo', 'horariosAtencion']);
    }

    /**
     * Cambia el estado del campo (HU-A4).
     * Registra el cambio en la bitácora de auditoría.
     */
    public function cambiarEstado(CampoDeportivo $campo, string $nuevoEstado): CampoDeportivo
    {
        $estadoAnterior = $campo->estado;
        $usuarioId = auth()->id();

        $campo->update(['estado' => $nuevoEstado]);

        AuditoriaService::registrar(
            tabla: 'campos_deportivos',
            registroId: $campo->id,
            accion: 'cambiar_estado',
            usuarioId: $usuarioId,
            datosAnteriores: ['estado' => $estadoAnterior],
            datosNuevos: ['estado' => $nuevoEstado],
        );

        return $campo->fresh();
    }
}
