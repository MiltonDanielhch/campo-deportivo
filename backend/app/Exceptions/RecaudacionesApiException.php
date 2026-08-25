<?php

namespace App\Exceptions;

use Exception;

/**
 * Se lanza cuando el Core de Recaudaciones no responde o responde con error.
 * Los módulos de reservas decidirán cómo degradar la experiencia
 * (ej. "intente nuevamente más tarde") ante esta excepción.
 */
class RecaudacionesApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $coreStatus = null,
    ) {
        parent::__construct($message);
    }

    public static function conexionFallida(string $detalle): self
    {
        return new self("No se pudo conectar con el Core de Recaudaciones: {$detalle}");
    }

    public static function respuestaInvalida(int $status, string $body): self
    {
        return new self(
            "El Core de Recaudaciones respondió con error {$status}: {$body}",
            $status,
        );
    }
}
