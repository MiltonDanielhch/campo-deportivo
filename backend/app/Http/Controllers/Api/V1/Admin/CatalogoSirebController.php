<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Services\AuditoriaService;
use App\Services\CatalogoSirebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Sireb\ClasificadorServicioSireb;
/**
 * Controller para gestión de integración SIREB en el panel administrativo.
 *
 * Endpoints:
 * - GET /api/v1/admin/catalogo-sireb/campos - Lista servicios SIREB con vinculación
 * - PATCH /api/v1/admin/campos-deportivos/{campoId}/vinculo-sireb - Vincular campo
 * - DELETE /api/v1/admin/campos-deportivos/{campoId}/vinculo-sireb - Desvincular campo
 * - POST /api/v1/admin/sireb/sincronizar-tarifas - Sincronizar tarifas manualmente
 */
class CatalogoSirebController extends Controller
{
    public function __construct(
        private CatalogoSirebService $catalogoSireb,
        private ClasificadorServicioSireb $clasificador,
    ) {
    }
    /**
     * GET /api/v1/admin/catalogo-sireb/campos
     *
     * Lista todos los servicios SIREB del catálogo con su campo local vinculado (si existe).
     * Permite al admin ver qué servicios están vinculados y cuáles no.
     *
     * P8.4 Fix: Ahora también detecta vínculos a través de la tabla arancel_variantes,
     * para que campos como Palacio o Estadio no aparezcan como "Sin campo local".
     */
    public function index(): JsonResponse
    {
        try {
            $catalogo = $this->catalogoSireb->catalogoCacheado();

            // 1. Campos con vínculo DIRECTO (1 a 1)
            $camposDirectos = CampoDeportivo::whereNotNull('servicio_sireb_id')
                ->get(['id', 'nombre', 'codigo', 'estado', 'direccion', 'servicio_sireb_id'])
                ->keyBy('servicio_sireb_id');

            // 2. Campos con vínculo por VARIANTE (1 a N)
            $variantes = ArancelVariante::whereNotNull('servicio_sireb_id')
                ->with('campo:id,nombre,codigo,estado,direccion')
                ->get([
                    'id',
                    'nombre',
                    'tipo_uso',
                    'unidad_medida',
                    'servicio_sireb_id',
                    'campo_id',
                ])
                ->keyBy('servicio_sireb_id');

            $data = collect($catalogo)->map(function ($servicio) use ($camposDirectos, $variantes) {
                $sirebId = $servicio['id'] ?? null;

                $clasificacion = $this->clasificador->clasificar($servicio);

                $vinculacion = 'sin_vincular';
                $tipoVinculacion = null;
                $varianteId = null;
                $campoLocal = null;

                if ($sirebId && $camposDirectos->has($sirebId)) {
                    // Caso A: Vínculo directo (1 campo = 1 servicio SIREB)
                    $vinculacion = 'vinculado';
                    $tipoVinculacion = 'directo';

                    $campo = $camposDirectos->get($sirebId);

                    $campoLocal = [
                        'id' => $campo->id,
                        'codigo' => $campo->codigo,
                        'nombre' => $campo->nombre,
                        'estado' => $campo->estado,
                        'direccion' => $campo->direccion,
                        'vinculacion_tipo' => 'directo',
                        'variante_id' => null,
                    ];
                } elseif ($sirebId && $variantes->has($sirebId)) {
                    // Caso B: Vínculo por variante (N servicios SIREB sobre 1 campo físico)
                    $vinculacion = 'vinculado';
                    $tipoVinculacion = 'variante';

                    $variante = $variantes->get($sirebId);
                    $campo = $variante->campo;

                    $varianteId = $variante->id;

                    $campoLocal = [
                        'id' => $campo->id,
                        'codigo' => $campo->codigo,
                        'nombre' => $campo->nombre . ' (vía ' . $variante->nombre . ')',
                        'estado' => $campo->estado,
                        'direccion' => $campo->direccion,
                        'vinculacion_tipo' => 'variante',
                        'variante_id' => $variante->id,
                        'variante_nombre' => $variante->nombre,
                        'variante_tipo_uso' => $variante->tipo_uso ?? null,
                        'variante_unidad_medida' => $variante->unidad_medida ?? null,
                    ];
                }

                $reservable = $this->esReservable($servicio);

                return [
                    'sireb' => [
                        'id' => $servicio['id'] ?? null,
                        'codigo' => $servicio['codigo'] ?? null,
                        'nombre' => $servicio['nombre'] ?? null,
                        'descripcion' => $servicio['descripcion'] ?? null,
                        'rubro' => $servicio['rubro'] ?? null,
                        'unidad_medida' => $servicio['unidad_medida'] ?? null,
                        'modo_tarifa' => $servicio['modo_tarifa'] ?? null,
                        'requiere_factura' => $servicio['requiere_factura'] ?? null,
                        'plazo_pago_dias' => $servicio['plazo_pago_dias'] ?? null,
                        'estado' => $servicio['estado'] ?? null,
                        'tarifario' => $servicio['tarifario'] ?? null,
                        'precio_min' => $this->calcularPrecioMin($servicio),
                        'precio_max' => $this->calcularPrecioMax($servicio),
                        'tarifas' => $servicio['tarifas'] ?? [],
                    ],

                    'campo_local' => $campoLocal,

                    'vinculacion' => $vinculacion,
                    'tipo_vinculacion' => $tipoVinculacion,
                    'variante_id' => $varianteId,

                    'clasificacion' => $clasificacion['categoria'],
                    'clasificacion_motivo' => $clasificacion['motivo'],

                    'accion_recomendada' => $this->accionRecomendada(
                        $clasificacion['categoria'],
                        $vinculacion,
                        $tipoVinculacion,
                    ),

                    'reservable_online' => $reservable,
                    'mensaje_no_reservable' => $reservable ? null : $this->motivoNoReservable($servicio),
                ];
            })->values()->all();

            $coleccion = collect($data);

            $totalServicios = $coleccion->count();
            $vinculados = $coleccion->where('vinculacion', 'vinculado')->count();
            $directos = $coleccion->where('tipo_vinculacion', 'directo')->count();
            $porVariantes = $coleccion->where('tipo_vinculacion', 'variante')->count();
            $sinVincular = $coleccion->where('vinculacion', 'sin_vincular')->count();

            return response()->json([
                'data' => $data,
                'meta' => [
                    'fuente' => 'SIREB',
                    'sincronizado_en' => $this->catalogoSireb->ultimaActualizacion(),
                    'total_servicios' => $totalServicios,
                    'vinculados' => $vinculados,
                    'vinculados_directos' => $directos,
                    'vinculados_por_variante' => $porVariantes,
                    'sin_vincular' => $sinVincular,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('sireb')->error('Error al obtener catálogo SIREB para admin', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'No se pudo obtener el catálogo de SIREB',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/v1/admin/campos-deportivos/{campoId}/vinculo-sireb
     *
     * Vincula un campo local a un servicio SIREB.
     */
    public function vincular(Request $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $validated = $request->validate([
            'servicio_sireb_id' => ['required', 'uuid'],
        ]);

        try {
            $servicio = $this->catalogoSireb->servicioPorId($validated['servicio_sireb_id']);

            if (!$servicio) {
                return response()->json([
                    'error' => 'Servicio no encontrado',
                    'message' => 'El servicio SIREB no existe en el catálogo.',
                ], 422);
            }

            // Verificar que el servicio no esté ya vinculado a otro campo (vínculo directo)
            $otroCampo = CampoDeportivo::where('servicio_sireb_id', $validated['servicio_sireb_id'])
                ->where('id', '!=', $campoDeportivo->id)
                ->first();

            if ($otroCampo) {
                return response()->json([
                    'error' => 'Servicio ya vinculado',
                    'message' => "Este servicio ya está vinculado directamente al campo '{$otroCampo->nombre}'.",
                ], 422);
            }

            // Nota: No bloqueamos si ya existe en arancel_variantes, porque ese es el comportamiento esperado
            // para campos que agrupan múltiples servicios bajo un mismo espacio físico.

            DB::beginTransaction();

            $campoDeportivo->update([
                'servicio_sireb_id' => $validated['servicio_sireb_id'],
                'servicio_sireb_codigo' => $servicio['codigo'] ?? null,
            ]);

            Log::channel('auditoria')->info('Campo vinculado a servicio SIREB', [
                'campo_id' => $campoDeportivo->id,
                'campo_codigo' => $campoDeportivo->codigo,
                'servicio_sireb_id' => $validated['servicio_sireb_id'],
                'servicio_sireb_codigo' => $servicio['codigo'] ?? null,
                'usuario' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Campo vinculado exitosamente al servicio SIREB',
                'data' => [
                    'campo_id' => $campoDeportivo->id,
                    'servicio_sireb_id' => $campoDeportivo->servicio_sireb_id,
                    'servicio_sireb_codigo' => $campoDeportivo->servicio_sireb_codigo,
                    'servicio_nombre' => $servicio['nombre'] ?? null,
                    'warning' => $this->generarWarningVinculacion($servicio),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('sireb')->error('Error al vincular campo a SIREB', [
                'campo_id' => $campoDeportivo->id,
                'servicio_sireb_id' => $validated['servicio_sireb_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al vincular campo',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/campos-deportivos/{campoId}/vinculo-sireb
     *
     * Desvincula un campo de su servicio SIREB.
     */
    public function desvincular(CampoDeportivo $campoDeportivo): JsonResponse
    {
        if (!$campoDeportivo->servicio_sireb_id) {
            return response()->json([
                'error' => 'Campo no vinculado',
                'message' => 'Este campo no tiene un servicio SIREB vinculado.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $servicioSirebId = $campoDeportivo->servicio_sireb_id;
            $servicioSirebCodigo = $campoDeportivo->servicio_sireb_codigo;

            $campoDeportivo->update([
                'servicio_sireb_id' => null,
                'servicio_sireb_codigo' => null,
            ]);

            AuditoriaService::registrar(
                tabla: 'campos_deportivos',
                registroId: $campoDeportivo->id,
                accion: 'desvincular_sireb',
                usuarioId: auth()->id(),
                datosAnteriores: [
                    'servicio_sireb_id' => $servicioSirebId,
                    'servicio_sireb_codigo' => $servicioSirebCodigo,
                ],
                datosNuevos: [
                    'servicio_sireb_id' => null,
                    'servicio_sireb_codigo' => null,
                ],
            );

            DB::commit();

            return response()->json([
                'message' => 'Campo desvinculado exitosamente del servicio SIREB',
                'data' => [
                    'campo_id' => $campoDeportivo->id,
                                        'aviso' => 'El campo dejará de aparecer en el listado público y no podrá reservarse online con las tarifas de ese servicio. Para volver a habilitarlo, vinculalo de nuevo.',
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('sireb')->error('Error al desvincular campo de SIREB', [
                'campo_id' => $campoDeportivo->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al desvincular campo',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/admin/sireb/sincronizar-tarifas
     */
    public function sincronizarTarifas(): JsonResponse
    {
        try {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('sireb:sincronizar-tarifas');
            $output = \Illuminate\Support\Facades\Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'message' => 'Sincronización ejecutada exitosamente',
                    'data' => ['output' => $output],
                ]);
            }

            return response()->json([
                'error' => 'Error en sincronización',
                'message' => $output,
            ], 500);
        } catch (\Throwable $e) {
            Log::channel('sireb')->error('Error al ejecutar sincronización de tarifas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al ejecutar sincronización',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/admin/catalogo-sireb/refrescar
     */
    public function refrescar(): JsonResponse
    {
        try {
            $catalogo = $this->catalogoSireb->refrescarCatalogo();

            return response()->json([
                'message' => 'Catálogo SIREB refrescado',
                'data' => [
                    'total_servicios' => count($catalogo),
                    'sincronizado_en' => $this->catalogoSireb->ultimaActualizacion(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('sireb')->error('Error al refrescar catálogo', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al refrescar catálogo',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ─── Helpers privados ───

    private function esReservable(array $servicio): bool
    {
        $estado = $servicio['estado'] ?? null;
        if ($estado !== null && !in_array($estado, ['activo', 'ACTIVO'], true)) {
            return false;
        }

        $tarifario = $servicio['tarifario'] ?? null;
        if ($tarifario === 'sin_tarifa') {
            return false;
        }

        $tarifas = $servicio['tarifas'] ?? [];
        return count($tarifas) > 0;
    }

    private function motivoNoReservable(array $servicio): string
    {
        $estado = $servicio['estado'] ?? null;
        if ($estado !== null && !in_array($estado, ['activo', 'ACTIVO'], true)) {
            return 'El servicio no está activo en recaudaciones.';
        }

        $tarifario = $servicio['tarifario'] ?? null;
        $tarifas = $servicio['tarifas'] ?? [];
        if ($tarifario === 'sin_tarifa' || count($tarifas) === 0) {
            return 'Este servicio no tiene tarifa liquidable. Consultá precio en ventanilla.';
        }

        return 'Este servicio no puede reservarse online en este momento.';
    }

    private function calcularPrecioMin(array $servicio): ?float
    {
        $precios = collect($servicio['tarifas'] ?? [])
            ->pluck('monto')
            ->filter()
            ->values();

        return $precios->isNotEmpty() ? (float) $precios->min() : null;
    }

    private function calcularPrecioMax(array $servicio): ?float
    {
        $precios = collect($servicio['tarifas'] ?? [])
            ->pluck('monto')
            ->filter()
            ->values();

        return $precios->isNotEmpty() ? (float) $precios->max() : null;
    }

    private function generarWarningVinculacion(array $servicio): ?string
    {
        $estado = $servicio['estado'] ?? null;
        if ($estado !== null && !in_array($estado, ['activo', 'ACTIVO'], true)) {
            return 'El servicio está inactivo en SIREB. El campo no será reservable online hasta que el servicio se active.';
        }

        $tarifario = $servicio['tarifario'] ?? null;
        if ($tarifario === 'sin_tarifa') {
            return 'El servicio no tiene tarifario liquidable. El campo no será reservable online.';
        }

        return null;
    }

    private function accionRecomendada(
        string $categoria,
        string $vinculacion,
        ?string $tipoVinculacion,
    ): string {
        if ($vinculacion === 'vinculado') {
            return $tipoVinculacion === 'variante'
                ? 'gestionar_variante'
                : 'desvincular_directo';
        }

        return match ($categoria) {
            ClasificadorServicioSireb::ALQUILER_BASE => 'crear_campo_simple',
            ClasificadorServicioSireb::SERVICIO_ADICIONAL => 'agregar_como_variante',
            ClasificadorServicioSireb::PISCINA => 'modulo_piscina',
            ClasificadorServicioSireb::ARRIENDO_PERIODICO,
            ClasificadorServicioSireb::HOSPEDAJE,
            ClasificadorServicioSireb::SERVICIO_ESPECIAL,
            ClasificadorServicioSireb::EVENTO_ESPECIAL,
            ClasificadorServicioSireb::NO_CLASIFICADO => 'fuera_de_alcance',
            default => 'revisar_clasificacion',
        };
    }
}
