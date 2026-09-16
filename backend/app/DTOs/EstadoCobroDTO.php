<?php

namespace App\DTOs;

/**
 * Estado de un cobro consultado al Core (polling, HU-D5).
 * - $pagado: si el Core ya confirmó el pago
 * - $montoConfirmado: monto efectivamente cobrado (puede diferir de monto_total)
 */
final class EstadoCobroDTO
{
    public function __construct(
        public readonly bool $pagado,
        public readonly ?float $montoConfirmado = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $estado = $data['estado'] ?? 'desconocido';
        $pagado = in_array($estado, ['pagado', 'confirmado', 'completed'], true);

        return new self(
            pagado: $pagado,
            montoConfirmado: isset($data['monto_confirmado']) ? (float) $data['monto_confirmado'] : null,
        );
    }
}
