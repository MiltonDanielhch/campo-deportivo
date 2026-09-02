<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTarifaRequest;
use App\Models\CampoDeportivo;
use App\Services\TarifaCampoService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints para el versionado de tarifas de un campo (HU-A3).
 * Cada campo tiene una sola tarifa activa a la vez; al crear una nueva,
 * la anterior se cierra automáticamente con vigente_hasta = now().
 */
class TarifaCampoController extends Controller
{
    public function __construct(
        private TarifaCampoService $service
    ) {}

    /**
     * POST /api/v1/campos-deportivos/{campoDeportivo}/tarifas
     * Crea una nueva tarifa para el campo. Cierra automáticamente la anterior.
     */
    public function store(StoreTarifaRequest $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $tarifa = $this->service->actualizarTarifa(
            $campoDeportivo,
            $request->validated('precio_por_hora')
        );

        return response()->json([
            'message' => 'Tarifa creada exitosamente. La tarifa anterior fue cerrada automáticamente.',
            'data' => $tarifa->load('creadoPor:id,nombre_completo'),
        ], 201);
    }

    /**
     * GET /api/v1/campos-deportivos/{campoDeportivo}/tarifas
     * Historial completo de tarifas del campo, ordenadas por vigencia descendente.
     */
    public function historial(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $historial = $this->service->historial($campoDeportivo);
        $tarifaActiva = $this->service->tarifaActiva($campoDeportivo);

        return response()->json([
            'data' => [
                'activa' => $tarifaActiva?->load('creadoPor:id,nombre_completo'),
                'historial' => $historial,
            ],
        ]);
    }
}
