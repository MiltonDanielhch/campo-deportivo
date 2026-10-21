<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Services\CatalogoSirebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                ->get(['id', 'nombre', 'servicio_sireb_id', 'campo_id'])
                ->keyBy('servicio_sireb_id');

            $data = collect($catalogo)->map(function ($servicio) use ($camposDirectos, $variantes) {
                $sirebId = $servicio['id'] ?? null;
                $vinculacion = 'sin_vincular';
                $campoLocal = null;

                if ($sirebId && $camposDirectos->has($sirebId)) {
                    // Caso A: Vínculo directo (ej. Cancha Sintética, Frontón)
                    $vinculacion = 'vinculado';
                    $campo = $camposDirectos->get($sirebId);
                    $campoLocal = [
                        'id' => $campo->id,
                        'codigo' => $campo->codigo,
                        'nombre' => $campo->nombre,
                        'estado' => $campo->estado,
                        'direccion' => $campo->direccion,
                    ];
                } elseif ($sirebId && $variantes->has($sirebId)) {
                    // Caso B: Vínculo a través de una variante (ej. Palacio, Estadio, Cancha Vieja)
                    $vinculacion = 'vinculado';
                    $variante = $variantes->get($sirebId);
                    $campo = $variante->campo; // Relación cargada por 'with'
                    $campoLocal = [
                        'id' => $campo->id,
                        'codigo' => $campo->codigo,
                        'nombre' => $campo->nombre . ' (vía ' . $variante->nombre . ')',
                        'estado' => $campo->estado,
                        'direccion' => $campo->direccion,
                    ];
                }

                $reservable = $this->esReservable($servicio);

                return [
                    'sireb' => [
                        'id' => $servicio['id'] ?? null,
                        'codigo' => $servicio['codigo'] ?? null,
                        'nombre' => $servicio['nombre'] ?? null,
                        'estado' => $servicio['estado'] ?? null,
                        'tarifario' => $servicio['tarifario'] ?? null,
                        'precio_min' => $this->calcularPrecioMin($servicio),
                        'precio_max' => $this->calcularPrecioMax($servicio),
                        'tarifas' => $servicio['tarifas'] ?? [],
                    ],
                    'campo_local' => $campoLocal,
                    'vinculacion' => $vinculacion,
                    'reservable_online' => $reservable,
                    'mensaje_no_reservable' => $reservable ? null : $this->motivoNoReservable($servicio),
                ];
            })->values()->all();

            $totalServicios = count($catalogo);
            $vinculados = collect($data)->where('vinculacion', 'vinculado')->count();
            $sinVincular = $totalServicios - $vinculados;

            return response()->json([
                'data' => $data,
                'meta' => [
                    'fuente' => 'SIREB',
                    'sincronizado_en' => $this->catalogoSireb->ultimaActualizacion(),
                    'total_servicios' => $totalServicios,
                    'vinculados' => $vinculados,
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

            Log::channel('auditoria')->info('Campo desvinculado de servicio SIREB', [
                'campo_id' => $campoDeportivo->id,
                'campo_codigo' => $campoDeportivo->codigo,
                'servicio_sireb_id' => $servicioSirebId,
                'servicio_sireb_codigo' => $servicioSirebCodigo,
                'usuario' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Campo desvinculado exitosamente del servicio SIREB',
                'data' => [
                    'campo_id' => $campoDeportivo->id,
                    'aviso' => 'El campo dejará de ser reservable online hasta que se vuelva a vincular.',
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
}
