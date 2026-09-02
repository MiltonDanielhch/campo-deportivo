<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;

class RolController extends Controller
{
    /**
     * GET /api/v1/roles
     * Lista los roles disponibles (para selects de alta de funcionarios).
     */
    public function index(): JsonResponse
    {
        $roles = Rol::orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion']);

        return response()->json(['data' => $roles]);
    }
}
