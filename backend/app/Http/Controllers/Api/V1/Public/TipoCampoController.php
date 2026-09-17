<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\TipoCampo;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint público para listar tipos de campo activos.
 * Sin autenticación: usado por el filtro de la web pública.
 */
class TipoCampoController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoCampo::where('estado', 'activo')
            ->select(['id', 'nombre'])
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $tipos,
        ]);
    }
}