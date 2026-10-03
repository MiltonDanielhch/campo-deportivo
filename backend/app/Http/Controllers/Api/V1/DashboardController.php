<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Dashboard principal (resumen del día).
 *
 * GET /api/v1/dashboard/resumen
 *
 * Accesible para todos los roles autenticados.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
    ) {
    }

    public function resumen(Request $request): JsonResponse
    {
        /** @var \App\Models\Funcionario|null $funcionario */
        $funcionario = $request->user();

        if (! $funcionario) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $resumen = $this->dashboard->resumenDelDia($funcionario);

        return response()->json([
            'data' => $resumen,
        ]);
    }
}
