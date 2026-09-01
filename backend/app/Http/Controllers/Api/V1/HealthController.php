<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Smoke test de conectividad del satélite de Canchas.
 * Público (sin autenticación): lo consumen el panel web, la app móvil
 * y los healthchecks de Docker/CI. No toca base de datos.
 */
class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'operational',
            'service' => config('app.name'),
            'version' => config('app.version', '0.1.0'),
            'timezone' => config('app.timezone'),
            'timestamp' => now()
                ->setTimezone(config('app.timezone', 'America/La_Paz'))
                ->toIso8601String(),
        ]);
    }
}
