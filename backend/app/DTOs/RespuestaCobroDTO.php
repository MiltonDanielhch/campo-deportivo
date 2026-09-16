<?php

namespace App\DTOs;

/**
 * Respuesta del Core al solicitar un cobro (forma v2.0.0 del roadmap).
 * El Core puede devolver QR renderizable (string o imagen) o un enlace
 * de checkout; la pantalla de pago acepta cualquiera de los dos.
 */
final class RespuestaCobroDTO
{
    public function __construct(
        public readonly string $referenciaRecaudaciones,
        public readonly ?string $qrString,
        public readonly ?string $qrImageBase64,
        public readonly ?string $checkoutUrl,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            referenciaRecaudaciones: (string) ($data['referencia_recaudaciones'] ?? $data['referencia'] ?? $data['id'] ?? ''),
            qrString: $data['qr_string'] ?? $data['qr'] ?? null,
            qrImageBase64: $data['qr_image_base64'] ?? null,
            checkoutUrl: $data['checkout_url'] ?? $data['url_pago'] ?? null,
        );
    }
}
