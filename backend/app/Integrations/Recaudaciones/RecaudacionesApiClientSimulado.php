<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCobroDTO;
use App\DTOs\WebhookPayloadDTO;
use App\Exceptions\RecaudacionesApiException;
use Illuminate\Http\Request;

/**
 * Cliente simulado EXCLUSIVO para desarrollo y pruebas.
 * Permite forzar éxito, fallo de conexión o error de respuesta
 * vía config('services.recaudaciones.simulado_modo'), sin depender
 * de que el Core esté disponible.
 *
 * NUNCA se registra en producción (ver AppServiceProvider).
 */
class RecaudacionesApiClientSimulado implements RecaudacionesApiClientInterface
{
    public function solicitarCobro(SolicitudCobroDTO $datos): RespuestaCobroDTO
    {
        $modo = config('services.recaudaciones.simulado_modo', 'exito');

        if ($modo === 'fallo_conexion') {
            throw RecaudacionesApiException::conexionFallida(
                'Simulado: no hay conexión con el Core de Recaudaciones',
            );
        }

        if ($modo === 'error_respuesta') {
            throw RecaudacionesApiException::respuestaInvalida(
                500,
                'Simulado: el Core devolvió un error interno',
            );
        }

        $sufijo = strtoupper(substr(md5($datos->referenciaExterna), 0, 10));

        return new RespuestaCobroDTO(
            referenciaRecaudaciones: 'CORE-SIM-' . $sufijo,
            qrString: 'https://core.gob.bo/pago/sim/' . $datos->referenciaExterna,
            qrImageBase64: null,
            checkoutUrl: 'https://core.gob.bo/checkout/sim/' . $datos->referenciaExterna,
        );
    }

    /**
     * Verificación simple para desarrollo y pruebas: un header fijo.
     * Suficiente para testear el flujo completo (401 incluido)
     * sin depender del Core real ni configurar HMAC.
     */
    public function verificarFirma(Request $request): bool
    {
        return $request->header('X-Test-Signature') === 'test';
    }

    public function parsearWebhook(Request $request): WebhookPayloadDTO
    {
        return WebhookPayloadDTO::fromArray($request->json()->all());
    }
}
