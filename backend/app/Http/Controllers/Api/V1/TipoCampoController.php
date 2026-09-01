<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTipoCampoRequest;
use App\Models\TipoCampo;
use App\Services\TipoCampoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoCampoController extends Controller
{
    public function __construct(
        private TipoCampoService $service
    ) {}

    /**
     * GET /api/v1/tipos-campo
     * Lista paginada con filtro opcional por estado.
     */
    public function index(Request $request): JsonResponse
    {
        $estado = $request->query('estado');
        $tipos = $this->service->listar($estado);

        return response()->json($tipos);
    }

    /**
     * GET /api/v1/tipos-campo/activos
     * Lista de tipos activos para selects (sin paginación).
     */
    public function activos(): JsonResponse
    {
        $tipos = $this->service->listarActivos();

        return response()->json(['data' => $tipos]);
    }

    /**
     * POST /api/v1/tipos-campo
     * Crea un nuevo tipo de campo.
     */
    public function store(StoreTipoCampoRequest $request): JsonResponse
    {
        $tipoCampo = $this->service->crear($request->validated());

        return response()->json([
            'message' => 'Tipo de campo creado exitosamente',
            'data' => $tipoCampo,
        ], 201);
    }

    /**
     * GET /api/v1/tipos-campo/{tipoCampo}
     * Detalle de un tipo de campo.
     */
    public function show(TipoCampo $tipoCampo): JsonResponse
    {
        return response()->json(['data' => $tipoCampo]);
    }

    /**
     * PUT /api/v1/tipos-campo/{tipoCampo}
     * Actualiza un tipo de campo existente.
     */
    public function update(StoreTipoCampoRequest $request, TipoCampo $tipoCampo): JsonResponse
    {
        $tipoCampo = $this->service->actualizar($tipoCampo, $request->validated());

        return response()->json([
            'message' => 'Tipo de campo actualizado exitosamente',
            'data' => $tipoCampo,
        ]);
    }

    /**
     * PATCH /api/v1/tipos-campo/{tipoCampo}/inhabilitar
     * Cambia el estado a 'inactivo' (no elimina físicamente).
     */
    public function inhabilitar(TipoCampo $tipoCampo): JsonResponse
    {
        if ($tipoCampo->estado === 'inactivo') {
            return response()->json([
                'message' => 'El tipo de campo ya está inactivo',
                'data' => $tipoCampo,
            ]);
        }

        $tipoCampo = $this->service->inhabilitar($tipoCampo);

        return response()->json([
            'message' => 'Tipo de campo inhabilitado exitosamente',
            'data' => $tipoCampo,
        ]);
    }

    /**
     * PATCH /api/v1/tipos-campo/{tipoCampo}/reactivar
     * Reactiva un tipo de campo previamente inhabilitado.
     */
    public function reactivar(TipoCampo $tipoCampo): JsonResponse
    {
        if ($tipoCampo->estado === 'activo') {
            return response()->json([
                'message' => 'El tipo de campo ya está activo',
                'data' => $tipoCampo,
            ]);
        }

        $tipoCampo = $this->service->reactivar($tipoCampo);

        return response()->json([
            'message' => 'Tipo de campo reactivado exitosamente',
            'data' => $tipoCampo,
        ]);
    }
}
