<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampoDeportivo;
use App\Services\CatalogoSirebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
     */
    public function index(): JsonResponse
    {
        try {
            $catalogo = $this->catalogoSireb->catalogoCacheado();
            $camposLocales = CampoDeportivo::all()->keyBy('servicio_sireb_id');

            $data = collect($catalogo)->map(function ($servicio) use ($camposLocales) {
                $campoLocal = $camposLocales->get($servicio['id']);

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
                    'campo_local' => $campoLocal ? [
                        'id' => $campoLocal->id,
                        'codigo' => $campoLocal->codigo,
                        'nombre' => $campoLocal->nombre,
                        'estado' => $campoLocal->estado,
                        'direccion' => $campoLocal->direccion,
                    ] : null,
                    'vinculacion' => $campoLocal ? 'vinculado' : 'sin_vincular',
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
     *
     * Reglas:
     * - El servicio SIREB debe existir en el catálogo
     * - No se puede vincular un servicio SIREB a más de un campo local
     * - Si el servicio está inactivo, permitir vínculo pero mostrar warning
     * - Si el servicio es sin_tarifa, permitir vínculo pero no será reservable online
     */
    public function vincular(Request $request, CampoDeportivo $campo): JsonResponse
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

            // Verificar que el servicio no esté ya vinculado a otro campo
            $otroCampo = CampoDeportivo::where('servicio_sireb_id', $validated['servicio_sireb_id'])
                ->where('id', '!=', $campo->id)
                ->first();

            if ($otroCampo) {
                return response()->json([
                    'error' => 'Servicio ya vinculado',
                    'message' => "Este servicio ya está vinculado al campo '{$otroCampo->nombre}'.",
                ], 422);
            }

            DB::beginTransaction();

            $campo->update([
                'servicio_sireb_id' => $validated['servicio_sireb_id'],
                'servicio_sireb_codigo' => $servicio['codigo'] ?? null,
            ]);

            // Auditoría
            Log::channel('auditoria')->info('Campo vinculado a servicio SIREB', [
                'campo_id' => $campo->id,
                'campo_codigo' => $campo->codigo,
                'servicio_sireb_id' => $validated['servicio_sireb_id'],
                'servicio_sireb_codigo' => $servicio['codigo'] ?? null,
                'usuario' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Campo vinculado exitosamente al servicio SIREB',
                'data' => [
                    'campo_id' => $campo->id,
                    'servicio_sireb_id' => $campo->servicio_sireb_id,
                    'servicio_sireb_codigo' => $campo->servicio_sireb_codigo,
                    'servicio_nombre' => $servicio['nombre'] ?? null,
                    'warning' => $this->generarWarningVinculacion($servicio),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('sireb')->error('Error al vincular campo a SIREB', [
                'campo_id' => $campo->id,
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
     * El campo dejará de ser reservable online hasta que se vuelva a vincular.
     */
    public function desvincular(CampoDeportivo $campo): JsonResponse
    {
        if (!$campo->servicio_sireb_id) {
            return response()->json([
                'error' => 'Campo no vinculado',
                'message' => 'Este campo no tiene un servicio SIREB vinculado.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $servicioSirebId = $campo->servicio_sireb_id;
            $servicioSirebCodigo = $campo->servicio_sireb_codigo;

            $campo->update([
                'servicio_sireb_id' => null,
                'servicio_sireb_codigo' => null,
            ]);

            // Auditoría
            Log::channel('auditoria')->info('Campo desvinculado de servicio SIREB', [
                'campo_id' => $campo->id,
                'campo_codigo' => $campo->codigo,
                'servicio_sireb_id' => $servicioSirebId,
                'servicio_sireb_codigo' => $servicioSirebCodigo,
                'usuario' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Campo desvinculado exitosamente del servicio SIREB',
                'data' => [
                    'campo_id' => $campo->id,
                    'aviso' => 'El campo dejará de ser reservable online hasta que se vuelva a vincular.',
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('sireb')->error('Error al desvincular campo de SIREB', [
                'campo_id' => $campo->id,
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
     *
     * Espeja en tarifas_campo el tarifario vigente de SIREB.
     * Es la ÚNICA vía por la que cambian los precios de este sistema.
     */
    public function sincronizarTarifas(): JsonResponse
    {
        try {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('sireb:sincronizar-tarifas');

            $output = \Illuminate\Support\Facades\Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'message' => 'Sincronización ejecutada exitosamente',
                    'data' => [
                        'output' => $output,
                    ],
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
