<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\EstadoCobroDTO;
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

    public function verificarFirma(Request $request): bool
    {
        return $request->header('X-Test-Signature') === 'test';
    }

    public function parsearWebhook(Request $request): WebhookPayloadDTO
    {
        return WebhookPayloadDTO::fromArray($request->json()->all());
    }

    /**
     * Simula consulta de estado para polling (HU-D5).
     * Usa config('services.recaudaciones.simulado_polling_modo'):
     * - 'pendiente': devuelve pagado=false
     * - 'pagado': devuelve pagado=true con monto
     * - 'no_existe': devuelve null
     */
    public function consultarEstado(string $referenciaRecaudaciones): ?EstadoCobroDTO
    {
        $modo = config('services.recaudaciones.simulado_polling_modo', 'pendiente');

        if ($modo === 'no_existe') {
            return null;
        }

        if ($modo === 'pagado') {
            return new EstadoCobroDTO(
                pagado: true,
                montoConfirmado: 300.00,
            );
        }

        return new EstadoCobroDTO(pagado: false);
    }
}
