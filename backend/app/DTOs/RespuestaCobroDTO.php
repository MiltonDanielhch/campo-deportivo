<?php

namespace App\DTOs;

/**
 * Respuesta del Core al solicitar un cobro.
 *
 * TODO: las claves exactas del payload del Core están pendientes de
 * confirmación con el equipo del Core; fromArray() mapea la forma
 * esperada por Canchas y se ajustará al llegar la documentación real.
 */
final class RespuestaCobroDTO
{
    public function __construct(
        public readonly string $solicitudCobroId,
        public readonly ?string $qrContenido,
        public readonly ?string $urlPago,
        public readonly int $montoTotal,
        public readonly string $estado,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            solicitudCobroId: (string) ($data['solicitud_cobro_id'] ?? $data['id'] ?? ''),
            qrContenido: $data['qr'] ?? null,
            urlPago: $data['url_pago'] ?? null,
            montoTotal: (int) ($data['monto_total'] ?? 0),
            estado: (string) ($data['estado'] ?? 'pendiente'),
        );
    }
}
