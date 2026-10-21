<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo oficial de ubicación del Beni para el panel administrativo.
 *
 * NO es el endpoint público /v1/public/ubicaciones, que deriva provincias/
 * municipios desde campos activos y visibles. Este lee config/beni.php para
 * que el operador pueda dar de alta el primer campo de una provincia nueva.
 */
class UbicacionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'provincias' => config('beni.provincias', []),
                'municipios' => config('beni.municipios_por_provincia', []),
            ],
        ]);
    }
}