<?php

namespace App\Services;

use App\Models\TipoCampo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TipoCampoService
{
    /**
     * Lista tipos de campo con paginación y filtros opcionales.
     */
    public function listar(?string $estado = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = TipoCampo::query();

        if ($estado) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('nombre')->paginate($perPage);
    }

    /**
     * Obtiene todos los tipos de campo activos (para selects en formularios).
     */
    public function listarActivos(): Collection
    {
        return TipoCampo::where('estado', 'activo')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Crea un nuevo tipo de campo.
     */
    public function crear(array $data): TipoCampo
    {
        return TipoCampo::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'estado' => 'activo',
        ]);
    }

    /**
     * Actualiza un tipo de campo existente.
     */
    public function actualizar(TipoCampo $tipoCampo, array $data): TipoCampo
    {
        $tipoCampo->update([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);

        return $tipoCampo->fresh();
    }

    /**
     * Inhabilita un tipo de campo (cambio de estado a 'inactivo').
     * No se elimina físicamente para preservar la integridad referencial.
     */
    public function inhabilitar(TipoCampo $tipoCampo): TipoCampo
    {
        $tipoCampo->update(['estado' => 'inactivo']);

        return $tipoCampo->fresh();
    }

    /**
     * Reactiva un tipo de campo previamente inhabilitado.
     */
    public function reactivar(TipoCampo $tipoCampo): TipoCampo
    {
        $tipoCampo->update(['estado' => 'activo']);

        return $tipoCampo->fresh();
    }
}
