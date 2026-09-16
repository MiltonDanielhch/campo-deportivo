<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * El Core de Recaudaciones no respondió (HU-D8). Distinto del 409:
 * acá la franja YA fue liberada (estado rechazada) y el ciudadano
 * puede reintentar más tarde con la misma selección.
 */
class ServicioDeCobroNoDisponibleException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'servicio_cobro_no_disponible',
        ], 503);
    }
}
