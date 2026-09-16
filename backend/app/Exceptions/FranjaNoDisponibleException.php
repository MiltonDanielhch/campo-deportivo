<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Una franja solicitada ya está cubierta por otra solicitud activa,
 * o la perdió por concurrencia en el mismo instante (EXCLUDE).
 * Se traduce a HTTP 409 Conflict — la app debe distinguirla del 503.
 */
class FranjaNoDisponibleException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'franja_no_disponible',
        ], 409);
    }
}
