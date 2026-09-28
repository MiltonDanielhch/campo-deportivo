<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampoPublicoResource;
use App\Models\CampoDeportivo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta pública de campos deportivos (Épica C, HU-C1).
 * Sin autenticación: cualquier ciudadano anónimo puede consultar.
 */
class CampoController extends Controller
{
    /**
     * Aplica las relaciones que necesita el Resource, con la tarifa
     * vigente (vigente_hasta NULL) ya filtrada.
     */
    private function conRelaciones(Builder $query): Builder
    {
        return $query->with([
            'tipoCampo',
            'horariosAtencion',
            'tarifas' => fn ($q) => $q->whereNull('vigente_hasta'),
        ]);
    }

    /**
     * Base común: solo campos visibles al público.
     * 'inactivo' queda excluido por completo.
     */
    private function visibles(): Builder
    {
        return CampoDeportivo::query()
            ->whereIn('estado', ['activo', 'mantenimiento']);
    }

    /**
     * GET /api/v1/public/campos
     * Listado público con filtros opcionales:
     *   ?tipo_campo_id=...     filtra por tipo
     *   ?buscar=...            busca (case-insensitive) en nombre y dirección
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->visibles();

        if ($request->filled('tipo_campo_id')) {
            $query->where('tipo_campo_id', $request->input('tipo_campo_id'));
        }

        if ($request->filled('buscar')) {
            $termino = trim((string) $request->input('buscar'));

            if ($termino !== '') {
                // Búsqueda case-insensitive en nombre O dirección.
                // PostgreSQL: ILIKE. MySQL/MariaDB: LIKE (ya es case-insensitive con collation default).
                $query->where(function (Builder $q) use ($termino): void {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . mb_strtolower($termino) . '%'])
                      ->orWhereRaw('LOWER(direccion) LIKE ?', ['%' . mb_strtolower($termino) . '%']);
                });
            }
        }

        return CampoPublicoResource::collection(
            $this->conRelaciones($query)->orderBy('nombre')->get(),
        );
    }

    /**
     * GET /api/v1/public/campos/{id}
     * Detalle con horarios y tarifa vigente. Un campo inactivo → 404.
     */
    public function show(string $campo): CampoPublicoResource
    {
        $campo = $this->conRelaciones($this->visibles())->findOrFail($campo);

        return new CampoPublicoResource($campo);
    }
}
