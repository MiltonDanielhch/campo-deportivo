<?php

namespace App\DTOs;

/**
 * Payload parseado de un webhook de confirmación del Core.
 * Lleva AMBAS referencias (la del Core y la externa de Canchas) por la
 * redundancia diseñada en el Módulo 4: el controller busca la solicitud
 * por cualquiera de las dos.
 */
final class WebhookPayloadDTO
{
    public function __construct(
        public readonly ?string $referenciaExterna,
        public readonly ?string $referenciaRecaudaciones,
        public readonly ?float $montoConfirmado,
        public readonly array $payloadCrudo,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            referenciaExterna: isset($data['referencia_externa'])
                ? (string) $data['referencia_externa']
                : null,
            referenciaRecaudaciones: isset($data['referencia_recaudaciones'])
                ? (string) $data['referencia_recaudaciones']
                : null,
            montoConfirmado: isset($data['monto_confirmado'])
                ? (float) $data['monto_confirmado']
                : null,
            payloadCrudo: $data,
        );
    }
}
