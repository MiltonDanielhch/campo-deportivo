<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFuncionarioRequest;
use App\Models\Funcionario;
use App\Services\FuncionarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoints de gestión de funcionarios (HU-B1).
 * Solo accesibles por admin_parametricas.
 */
class FuncionarioController extends Controller
{
    public function __construct(
        private FuncionarioService $service
    ) {}

    /**
     * GET /api/v1/funcionarios
     * Listado con filtros opcionales por rol y estado.
     */
    public function index(Request $request): JsonResponse
    {
        $funcionarios = $this->service->listar(
            rolId: $request->query('rol_id'),
            estado: $request->query('estado'),
        );

        return response()->json($funcionarios);
    }

    /**
     * GET /api/v1/funcionarios/{funcionario}
     */
    public function show(Funcionario $funcionario): JsonResponse
    {
        return response()->json([
            'data' => $funcionario->load('rol:id,nombre'),
        ]);
    }

    /**
     * POST /api/v1/funcionarios
     * Crea un funcionario nuevo.
     */
    public function store(StoreFuncionarioRequest $request): JsonResponse
    {
        $funcionario = $this->service->crear($request->validated());

        return response()->json([
            'message' => 'Funcionario creado exitosamente',
            'data' => $funcionario,
        ], 201);
    }

    /**
     * PUT /api/v1/funcionarios/{funcionario}
     * Actualiza datos generales (NO el estado).
     */
    public function update(StoreFuncionarioRequest $request, Funcionario $funcionario): JsonResponse
    {
        $funcionario = $this->service->actualizar($funcionario, $request->validated());

        return response()->json([
            'message' => 'Funcionario actualizado exitosamente',
            'data' => $funcionario,
        ]);
    }

    /**
     * PATCH /api/v1/funcionarios/{funcionario}/estado
     * Activa o inactiva un funcionario.
     */
    public function cambiarEstado(Request $request, Funcionario $funcionario): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ]);

        if ($funcionario->estado === $validated['estado']) {
            return response()->json([
                'message' => 'El funcionario ya se encuentra en ese estado',
                'data' => $funcionario->load('rol:id,nombre'),
            ]);
        }

        $funcionario = $this->service->cambiarEstado($funcionario, $validated['estado']);

        return response()->json([
            'message' => 'Estado del funcionario actualizado',
            'data' => $funcionario,
        ]);
    }
}
