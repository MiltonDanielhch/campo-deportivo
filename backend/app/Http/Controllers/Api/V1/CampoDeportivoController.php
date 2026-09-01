<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CrearCampoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampoDeportivoRequest;
use App\Models\CampoDeportivo;
use App\Services\CampoDeportivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampoDeportivoController extends Controller
{
    public function __construct(
        private CampoDeportivoService $service
    ) {}

    /**
     * GET /api/v1/campos-deportivos
     */
    public function index(Request $request): JsonResponse
    {
        $campos = $this->service->listar(
            tipoCampoId: $request->query('tipo_campo_id'),
            estado: $request->query('estado'),
        );

        return response()->json($campos);
    }

    /**
     * GET /api/v1/campos-deportivos/{campoDeportivo}
     */
    public function show(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $campo = $this->service->obtenerDetalle($campoDeportivo);

        return response()->json(['data' => $campo]);
    }

    /**
     * POST /api/v1/campos-deportivos
     */
    public function store(StoreCampoDeportivoRequest $request): JsonResponse
    {
        $dto = CrearCampoDTO::fromArray($request->validated());
        $campo = $this->service->crear($dto);

        return response()->json([
            'message' => 'Campo deportivo creado exitosamente',
            'data' => $campo,
        ], 201);
    }

    /**
     * PUT /api/v1/campos-deportivos/{campoDeportivo}
     * Edita datos generales, NO el estado.
     */
    public function update(StoreCampoDeportivoRequest $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $campo = $this->service->actualizar($campoDeportivo, $request->validated());

        return response()->json([
            'message' => 'Campo deportivo actualizado exitosamente',
            'data' => $campo,
        ]);
    }

    /**
     * PATCH /api/v1/campos-deportivos/{campoDeportivo}/estado
     * Cambia el estado (activo/mantenimiento/inactivo).
     */
    public function cambiarEstado(Request $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', Rule::in(['activo', 'mantenimiento', 'inactivo'])],
        ]);

        if ($campoDeportivo->estado === $validated['estado']) {
            return response()->json([
                'message' => 'El campo ya se encuentra en ese estado',
                'data' => $campoDeportivo,
            ]);
        }

        $campo = $this->service->cambiarEstado($campoDeportivo, $validated['estado']);

        return response()->json([
            'message' => 'Estado del campo actualizado',
            'data' => $campo,
        ]);
    }
}
