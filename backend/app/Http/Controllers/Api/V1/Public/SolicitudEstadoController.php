<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\SolicitudEstadoResource;
use App\Models\SolicitudReserva;
use Illuminate\Http\JsonResponse;

/**
 * Consulta pública del estado de una solicitud (HU-D7).
 * Sin autenticación: el ciudadano consulta con su código de seguimiento.
 */
class SolicitudEstadoController extends Controller
{
    public function show(string $codigoSeguimiento): JsonResponse
    {
        $solicitud = SolicitudReserva::where('codigo_seguimiento', $codigoSeguimiento)
            ->with('reservas.campo')
            ->first();

        if (! $solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        return response()->json([
            'data' => new SolicitudEstadoResource($solicitud),
        ]);
    }
}
