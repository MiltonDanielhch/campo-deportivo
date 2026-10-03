<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OcupacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de ocupación para control en sitio (HU-F1).
 *
 * Endpoints:
 * - GET /api/v1/ocupacion/mis-campos?fecha=YYYY-MM-DD
 * - GET /api/v1/ocupacion/verificar/{codigo_reserva}
 *
 * Restricción server-side: funcionario_control solo ve sus campos asignados.
 */
class OcupacionController extends Controller
{
    public function __construct(
        private OcupacionService $ocupacion,
    ) {
    }

    /**
     * GET /api/v1/ocupacion/mis-campos?fecha=YYYY-MM-DD
     *
     * Devuelve los campos asignados al funcionario con su ocupación del día.
     * Si es admin, devuelve todos los campos activos.
     */
    public function misCampos(Request $request): JsonResponse
    {
        $fechaStr = $request->query('fecha', now()->toDateString());

        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $fechaStr)->startOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fecha inválida. Use formato YYYY-MM-DD.',
            ], 422);
        }

        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        $campos = $this->ocupacion->misCampos($funcionario, $fecha);

        return response()->json([
            'data' => $campos,
            'meta' => [
                'fecha' => $fecha->toDateString(),
                'total_campos' => count($campos),
            ],
        ]);
    }

    /**
     * GET /api/v1/ocupacion/verificar/{codigo_reserva}
     *
     * Verifica un código de reserva. Si el funcionario es funcionario_control
     * y el campo no está entre sus asignados, responde 404 (no filtra existencia).
     */
    public function verificar(Request $request, string $codigo): JsonResponse
    {
        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        $resultado = $this->ocupacion->verificarCodigo($codigo, $funcionario);

        if (! $resultado) {
            return response()->json([
                'message' => 'Código de reserva no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $resultado,
        ]);
    }

    /**
     * GET /api/v1/ocupacion/mapa-global?fecha=YYYY-MM-DD
     *
     * Devuelve todos los campos activos o en mantenimiento con su ocupación
     * del día, coordenadas y metadatos administrativos (vínculo SIREB).
     * Solo accesible para admin_parametricas y gerencia.
     */
    public function mapaGlobal(Request $request): JsonResponse
    {
        $fechaStr = $request->query('fecha', now()->toDateString());

        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $fechaStr)->startOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fecha inválida. Use formato YYYY-MM-DD.',
            ], 422);
        }

        $campos = $this->ocupacion->mapaGlobal($fecha);

        return response()->json([
            'data' => $campos,
            'meta' => [
                'fecha' => $fecha->toDateString(),
                'total_campos' => count($campos),
                'activos' => count(array_filter($campos, fn ($c) => $c['estado_operativo'] === 'activo')),
                'en_mantenimiento' => count(array_filter($campos, fn ($c) => $c['estado_operativo'] === 'mantenimiento')),
                'vinculados_sireb' => count(array_filter($campos, fn ($c) => $c['vinculacion_sireb']['vinculado'])),
            ],
        ]);
    }
}
