<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Una franja solicitada ya está cubierta por otra solicitud activa,
 * o se perdió por concurrencia en el mismo instante (EXCLUDE).
 * HTTP 409 Conflict — la app la distingue del 503 de cobro caído.
 *
 * $franja (opcional) identifica estructuralmente la franja en
 * conflicto para que la app la quite del carrito (Fase 4.3).
 */
class FranjaNoDisponibleException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?array $franja = null,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'franja_no_disponible',
            'franja' => $this->franja,
        ], 409);
    }
}
