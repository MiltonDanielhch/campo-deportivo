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
     *
     * Historial + tarifas activas horarias + tarifas espejadas no horarias
     * + última sincronización desde SIREB.
     */
    public function historial(CampoDeportivo $campoDeportivo): JsonResponse
    {
        return response()->json([
            'data' => $this->service->panel($campoDeportivo),
        ]);
    }
}
