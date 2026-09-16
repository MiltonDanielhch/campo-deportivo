<?php

namespace App\DTOs;

/**
 * Lo que Canchas le envía al Core para pedir un cobro.
 * referenciaExterna = el codigo_seguimiento de Canchas (doble
 * referencia para el matching del webhook en el módulo siguiente).
 */
final class SolicitudCobroDTO
{
    public function __construct(
        public readonly string $referenciaExterna,
        public readonly float $monto,
        public readonly string $nombrePagador,
        public readonly string $telefonoPagador,
        public readonly ?string $ciNitPagador,
        public readonly string $descripcion,
    ) {}
}
