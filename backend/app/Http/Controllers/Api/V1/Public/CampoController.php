<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\CampoDeportivo;
use App\Services\CampoPublicoSirebPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Enums\ModalidadReservaCampo;

/**
 * Consulta pública de campos deportivos (Épica C, HU-C1).
 *
 * Fase catálogo SIREB:
 * - Los precios provienen del catálogo de SIREB/Paitití cuando está disponible.
 * - Si SIREB falla, se usa tarifas_campo local como fallback.
 * - El frontend público nunca llama directo a SIREB.
 */
class CampoController extends Controller
{
    public function __construct(
        private CampoPublicoSirebPresenter $presenter,
    ) {
    }

    /**
     * Aplica las relaciones que necesita el presenter, con la tarifa
     * vigente local como respaldo.
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
     *
     * Bug A (Fase 2): el público NO puede reservar
     *  - campos "padre de variantes" (CV-001 / PAL-001 / EGM-001): tienen
     *    servicio_sireb_id null y N servicios espejados, entonces
     *    calcularGrilla no resuelve un único precio y la grilla sale gris.
     *  - campos solo-ventanilla (sin_reserva_online).
     * Se mantienen la piscina (turno_por_perfil) y los legacy (CS1/CS3),
     * que no tienen filas activas en arancel_variantes.
     *
     * El NULL en modalidad_reserva se trata como "no es sin_reserva_online"
     * (NOT IN / <> con NULL descartaría campos legacy sin modalidad set).
     *
     * TODO P8.5: cuando existan variantes visible_publico=true, revisar este
     * filtro para no esconder un campo que sí tenga variante pública.
     */
    private function visibles(): Builder
    {
        return CampoDeportivo::query()
            ->whereIn('estado', ['activo', 'mantenimiento'])
            ->where(fn ($q) => $q
                ->whereNull('modalidad_reserva')
                ->orWhere('modalidad_reserva', '<>', ModalidadReservaCampo::SinReservaOnline->value))
            ->whereDoesntHave('variantes', fn ($q) => $q->where('activo', true));
    }

    /**
     * GET /api/v1/public/campos
     * Listado público con filtros opcionales:
     *   ?tipo_campo_id=...     filtra por tipo
     *   ?buscar=...            busca (case-insensitive) en nombre y dirección
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->visibles();

        if ($request->filled('tipo_campo_id')) {
            $query->where('tipo_campo_id', $request->input('tipo_campo_id'));
        }

        if ($request->filled('buscar')) {
            $termino = trim((string) $request->input('buscar'));

            if ($termino !== '') {
                $query->where(function (Builder $q) use ($termino): void {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . mb_strtolower($termino) . '%'])
                      ->orWhereRaw('LOWER(direccion) LIKE ?', ['%' . mb_strtolower($termino) . '%']);
                });
            }
        }

        $campos = $this->conRelaciones($query)
            ->orderBy('nombre')
            ->get();

        return response()->json($this->presenter->index($campos));
    }

    /**
     * GET /api/v1/public/campos/{id}
     * Detalle con horarios y tarifa vigente desde SIREB.
     * Un campo inactivo → 404.
     */
    public function show(string $campo): JsonResponse
    {
        $campoModel = $this->conRelaciones($this->visibles())
            ->findOrFail($campo);

        return response()->json($this->presenter->show($campoModel));
    }
}
