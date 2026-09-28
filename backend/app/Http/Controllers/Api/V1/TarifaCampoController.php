<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTarifaRequest;
use App\Models\CampoDeportivo;
use App\Services\TarifaCampoService;
use Illuminate\Http\JsonResponse;

class TarifaCampoController extends Controller
{
    public function __construct(
        private TarifaCampoService $service
    ) {}

    /**
     * POST /api/v1/campos-deportivos/{campoDeportivo}/tarifas
     * Crea una nueva tarifa (diurna o nocturna) cerrando la activa del mismo tipo.
     */
    public function store(StoreTarifaRequest $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $tarifa = $this->service->actualizarTarifa(
            $campoDeportivo,
            $request->validated('tipo_tarifa'),
            (float) $request->validated('precio_por_hora'),
        );

        return response()->json([
            'message' => sprintf(
                'Tarifa %s creada. La anterior del mismo tipo fue cerrada automáticamente.',
                $tarifa->tipo_tarifa,
            ),
            'data' => $tarifa->load('creadoPor:id,nombre_completo'),
        ], 201);
    }

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
