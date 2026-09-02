<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Services\AsignacionFuncionarioService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints de asignación de campos a funcionarios (HU-B3).
 * Regla de negocio: solo funcionario_control puede recibir asignaciones.
 */
class AsignacionFuncionarioController extends Controller
{
    public function __construct(
        private AsignacionFuncionarioService $service
    ) {}

    /**
     * POST /api/v1/funcionarios/{funcionario}/campos/{campoDeportivo}
     * Asigna un campo a un funcionario.
     */
    public function asignar(Funcionario $funcionario, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $asignacion = $this->service->asignar($funcionario, $campoDeportivo);

        return response()->json([
            'message' => 'Campo asignado correctamente',
            'data' => $asignacion,
        ], 201);
    }

    /**
     * DELETE /api/v1/funcionarios/{funcionario}/campos/{campoDeportivo}
     * Desasigna un campo de un funcionario.
     */
    public function desasignar(Funcionario $funcionario, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $this->service->desasignar($funcionario, $campoDeportivo);

        return response()->json([
            'message' => 'Campo desasignado correctamente',
        ]);
    }

    /**
     * GET /api/v1/funcionarios/{funcionario}/campos
     * Lista los campos asignados a un funcionario específico.
     */
    public function porFuncionario(Funcionario $funcionario): JsonResponse
    {
        $campos = $this->service->porFuncionario($funcionario);

        return response()->json(['data' => $campos]);
    }

    /**
     * GET /api/v1/asignaciones
     * Lista todos los funcionarios con rol funcionario_control
     * que tienen al menos una asignación.
     */
    public function index(): JsonResponse
    {
        $funcionarios = $this->service->funcionariosConAsignaciones();

        return response()->json(['data' => $funcionarios]);
    }
}
