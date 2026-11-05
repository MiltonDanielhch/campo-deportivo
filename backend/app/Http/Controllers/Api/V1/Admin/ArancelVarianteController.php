<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use App\Services\AuditoriaService;
use App\Services\CatalogoSirebService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Variantes arancelarias de un campo físico.
 *
 * P8.2C: lectura para el modal de ventanilla.
 * BLOQUE 2: escritura (crear/editar/desvincular variante).
 *
 * ⟦FIX servicio-adicional⟧ index() y serviciosAdicionales() leen tarifas con
 * criterios DISTINTOS y NO se puede compartir un flag con default (se aplica a
 * medias en silencio → el test de servicio adicional da count 0). Cada método
 * arma su propia query de tarifas por nombre y se la pasa a respondCon():
 *   - tarifasHorarias()  → paraGrillaHoraria() (diurna/nocturna/unica, sin perfil).
 *   - tarifasServicio()  → vigente + liquidable, SIN el scope: limpieza/energía
 *     son no-horarias y el scope las excluye (SIREB las cobra en ventanilla con
 *     boleta, no en grilla).
 */
class ArancelVarianteController extends Controller
{
    public function __construct(
        private CatalogoSirebService $catalogoSireb,
    ) {
    }

    public function index(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $variantes = ArancelVariante::query()
            ->where('campo_id', $campoDeportivo->id)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return $this->respondCon(
            $campoDeportivo,
            $variantes,
            $this->tarifasHorarias($campoDeportivo),
        );
    }

    /**
     * Servicios adicionales de un campo físico.
     *
     * P8.3A: limpieza, energía, etc.
     * No son franjas horarias y no ocupan disponibilidad física.
     */
    public function serviciosAdicionales(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $variantes = ArancelVariante::query()
            ->where('campo_id', $campoDeportivo->id)
            ->where('activo', true)
            ->where(function ($query) {
                $query->where('unidad_medida', 'servicio')
                    ->orWhere('tipo_uso', 'servicio_adicional');
            })
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return $this->respondCon(
            $campoDeportivo,
            $variantes,
            $this->tarifasServicio($campoDeportivo),
        );
    }

    /**
     * POST /api/v1/admin/campos/{campoDeportivo}/variantes
     */
    public function store(Request $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('arancel_variantes', 'codigo')->where('campo_id', $campoDeportivo->id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tipo_uso' => ['required', 'string', 'max:40'],
            'servicio_sireb_id' => [
                'nullable', 'uuid',
                Rule::unique('arancel_variantes', 'servicio_sireb_id'),
            ],
            'unidad_medida' => ['required', 'string', 'max:30'],
            'visible_publico' => ['boolean'],
            'activo' => ['boolean'],
            'orden' => ['integer', 'min:0'],
        ]);

        $servicioSirebCodigo = null;

        if (!empty($validated['servicio_sireb_id'])) {
            $servicio = $this->catalogoSireb->servicioPorId($validated['servicio_sireb_id']);

            if (!$servicio) {
                return response()->json([
                    'error' => 'Servicio no encontrado',
                    'message' => 'El servicio SIREB no existe en el catálogo vigente.',
                ], 422);
            }

            $servicioSirebCodigo = $servicio['codigo'] ?? null;
        }

        $variante = DB::transaction(function () use ($campoDeportivo, $validated, $servicioSirebCodigo) {
            return ArancelVariante::create([
                'campo_id' => $campoDeportivo->id,
                'codigo' => $validated['codigo'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'tipo_uso' => $validated['tipo_uso'],
                'servicio_sireb_id' => $validated['servicio_sireb_id'] ?? null,
                'servicio_sireb_codigo' => $servicioSirebCodigo,
                'unidad_medida' => $validated['unidad_medida'],
                'visible_publico' => $validated['visible_publico'] ?? false,
                'activo' => $validated['activo'] ?? true,
                'orden' => $validated['orden'] ?? 0,
            ]);
        });

        AuditoriaService::registrar(
            tabla: 'arancel_variantes',
            registroId: $variante->id,
            accion: 'crear_variante',
            usuarioId: $this->usuarioId($request), // [FIX P1013 v2] $request->user(), no auth()
            datosAnteriores: null,
            datosNuevos: $validated + ['campo_id' => $campoDeportivo->id, 'servicio_sireb_codigo' => $servicioSirebCodigo],
        );

        return response()->json([
            'message' => 'Variante creada. Ejecutá la sincronización de tarifas para espejar sus precios desde SIREB.',
            'data' => $variante,
        ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * PUT /api/v1/admin/campos/{campoDeportivo}/variantes/{variante}
     */
    public function update(Request $request, CampoDeportivo $campoDeportivo, ArancelVariante $variante): JsonResponse
    {
        abort_unless($variante->campo_id === $campoDeportivo->id, 404);

        $validated = $request->validate([
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('arancel_variantes', 'codigo')
                    ->where('campo_id', $campoDeportivo->id)
                    ->ignore($variante->id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tipo_uso' => ['required', 'string', 'max:40'],
            'servicio_sireb_id' => [
                'nullable', 'uuid',
                Rule::unique('arancel_variantes', 'servicio_sireb_id')->ignore($variante->id),
            ],
            'unidad_medida' => ['required', 'string', 'max:30'],
            'visible_publico' => ['boolean'],
            'activo' => ['boolean'],
            'orden' => ['integer', 'min:0'],
        ]);

        $servicioSirebCodigo = $variante->servicio_sireb_codigo;

        if (!empty($validated['servicio_sireb_id']) && $validated['servicio_sireb_id'] !== $variante->servicio_sireb_id) {
            $servicio = $this->catalogoSireb->servicioPorId($validated['servicio_sireb_id']);

            if (!$servicio) {
                return response()->json([
                    'error' => 'Servicio no encontrado',
                    'message' => 'El servicio SIREB no existe en el catálogo vigente.',
                ], 422);
            }

            $servicioSirebCodigo = $servicio['codigo'] ?? null;
        } elseif (empty($validated['servicio_sireb_id'])) {
            $servicioSirebCodigo = null;
        }

        $anterior = $variante->only([
            'codigo', 'nombre', 'tipo_uso', 'servicio_sireb_id',
            'unidad_medida', 'visible_publico', 'activo', 'orden',
        ]);

        $variante->update([
            'codigo' => $validated['codigo'],
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'tipo_uso' => $validated['tipo_uso'],
            'servicio_sireb_id' => $validated['servicio_sireb_id'] ?? null,
            'servicio_sireb_codigo' => $servicioSirebCodigo,
            'unidad_medida' => $validated['unidad_medida'],
            'visible_publico' => $validated['visible_publico'] ?? $variante->visible_publico,
            'activo' => $validated['activo'] ?? $variante->activo,
            'orden' => $validated['orden'] ?? $variante->orden,
        ]);

        AuditoriaService::registrar(
            tabla: 'arancel_variantes',
            registroId: $variante->id,
            accion: 'actualizar_variante',
            usuarioId: $this->usuarioId($request), // [FIX P1013 v2] $request->user(), no auth()
            datosAnteriores: $anterior,
            datosNuevos: $variante->only([
                'codigo', 'nombre', 'tipo_uso', 'servicio_sireb_id',
                'unidad_medida', 'visible_publico', 'activo', 'orden',
            ]),
        );

        return response()->json([
            'message' => 'Variante actualizada.',
            'data' => $variante->fresh(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * DELETE /api/v1/admin/campos/{campoDeportivo}/variantes/{variante}
     *
     * Desvincula la variante del servicio SIREB y la desactiva, SIN borrar
     * físico (respeta FK desde solicitud_reserva_detalle) y SIN tocar el campo
     * padre. Libera la unique de servicio_sireb_id para poder re-usar el servicio.
     *
     * [FIX P1013 v2] gana Request $request como primer parámetro: Laravel lo
     * inyecta por type-hint y los modelos siguen bindeándose por segmento de
     * ruta ({campoDeportivo}/{variante}), así que routes/api.php NO cambia.
     */
    public function destroy(Request $request, CampoDeportivo $campoDeportivo, ArancelVariante $variante): JsonResponse
    {
        abort_unless($variante->campo_id === $campoDeportivo->id, 404);

        $anterior = $variante->only(['activo', 'servicio_sireb_id', 'servicio_sireb_codigo']);

        $variante->update([
            'activo' => false,
            'servicio_sireb_id' => null,
            'servicio_sireb_codigo' => null,
        ]);

        AuditoriaService::registrar(
            tabla: 'arancel_variantes',
            registroId: $variante->id,
            accion: 'desvincular_variante',
            usuarioId: $this->usuarioId($request), // [FIX P1013 v2] $request->user(), no auth()
            datosAnteriores: $anterior,
            datosNuevos: ['activo' => false, 'servicio_sireb_id' => null, 'servicio_sireb_codigo' => null],
        );

        return response()->json([
            'message' => 'Variante desvinculada y desactivada. El campo padre no se modificó.',
            'data' => $variante->fresh(),
        ]);
    }

    // ─── Queries de tarifas por naturaleza (sin defaults traicioneros) ───

    /** Horarias: diurna/nocturna/unica, sin perfil. Para index(). */
    private function tarifasHorarias(CampoDeportivo $campo): Builder
    {
        return TarifaCampo::paraGrillaHoraria()
            ->where('campo_id', $campo->id);
    }

    /** No-horarias (limpieza/energía): vigente + liquidable, SIN el scope.
     *  Para serviciosAdicionales(). El scope excluiría estas tarifas → count 0. */
    private function tarifasServicio(CampoDeportivo $campo): Builder
    {
        return TarifaCampo::query()
            ->where('campo_id', $campo->id)
            ->whereNull('vigente_hasta')
            ->where('liquidable', true)
            ->where('tarifario_estado', 'vigente');
    }

    /**
     * Mapeo único de variantes→tarifas. Recibe la query YA armada por el caller,
     * así no hay flag con default que se pueda aplicar a medias.
     */
    private function respondCon(
        CampoDeportivo $campoDeportivo,
        Collection $variantes,
        Builder $tarifasQuery,
    ): JsonResponse {
        $servicioIds = $variantes
            ->pluck('servicio_sireb_id')
            ->filter()
            ->unique()
            ->values();

        $tarifasPorServicio = collect();

        if ($servicioIds->isNotEmpty()) {
            $tarifasPorServicio = $tarifasQuery
                ->whereIn('servicio_sireb_id', $servicioIds)
                ->get()
                ->groupBy('servicio_sireb_id');
        }

        return response()->json([
            'data' => $variantes->map(function (ArancelVariante $variante) use ($tarifasPorServicio) {
                $tarifas = $tarifasPorServicio->get($variante->servicio_sireb_id, collect());

                return [
                    'id' => $variante->id,
                    'codigo' => $variante->codigo,
                    'nombre' => $variante->nombre,
                    'descripcion' => $variante->descripcion,
                    'tipo_uso' => $variante->tipo_uso,
                    'tipo_asociacion' => $variante->tipo_asociacion,
                    'servicio_sireb_id' => $variante->servicio_sireb_id,
                    'servicio_sireb_codigo' => $variante->servicio_sireb_codigo,
                    'unidad_medida' => $variante->unidad_medida,
                    'visible_publico' => $variante->visible_publico,
                    'activo' => $variante->activo,
                    'orden' => $variante->orden,
                    'tarifas' => $tarifas->map(fn (TarifaCampo $tarifa) => [
                        'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                        'etiqueta' => $tarifa->etiqueta_sireb,
                        'clasificacion' => $tarifa->clasificacion,
                        'monto' => (float) $tarifa->precio_por_hora,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * [FIX P1013 v2] auth() sin args devuelve Factory/AuthManager, cuyo __call
     * mágico no se resuelve estáticamente (por eso auth()->id() Y auth()->user()
     * dan P1013). Request::user() SÍ está declarado en Illuminate\Http\Request
     * (Authorizable|null); el @var lo angosta a Funcionario (HasUuids → id
     * string). Mismo patrón que OcupacionController / SolicitudReservaController,
     * que no dan warning.
     */
    private function usuarioId(Request $request): ?string
    {
        /** @var \App\Models\Funcionario|null $usuario */
        $usuario = $request->user();

        return $usuario?->id;
    }
}
