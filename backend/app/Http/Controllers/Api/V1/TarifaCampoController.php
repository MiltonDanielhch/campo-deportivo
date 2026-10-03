<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CampoDeportivo;
use App\Services\TarifaCampoService;
use Illuminate\Http\JsonResponse;

/**
 * Tarifas de un campo deportivo (HU-A3).
 *
 * Solo lectura: el precio lo define SIREB, que es la fuente de verdad del
 * contrato de recaudaciones. Este sistema mantiene tarifas_campo como espejo
 * validado mediante `php artisan sireb:sincronizar-tarifas`, y no expone
 * ninguna vía para fijar precios a mano.
 */
class TarifaCampoController extends Controller
{
    public function __construct(
        private TarifaCampoService $service
    ) {}

    /**
     * GET /api/v1/campos-deportivos/{campoDeportivo}/tarifas
     * Historial + tarifas activas (diurna y nocturna) + hora de corte.
     */
    public function historial(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $activas = $this->service->tarifasActivas($campoDeportivo);
        $historial = $this->service->historial($campoDeportivo);

        // Cargar la relación en ambas activas (si existen)
        foreach ($activas as $tarifa) {
            $tarifa?->load('creadoPor:id,nombre_completo');
        }

        return response()->json([
            'data' => [
                'hora_inicio_noche' => $campoDeportivo->hora_inicio_noche,
                'activas' => [
                    'diurna' => $activas['diurna'],
                    'nocturna' => $activas['nocturna'],
                ],
                'historial' => $historial,
            ],
        ]);
    }
}
