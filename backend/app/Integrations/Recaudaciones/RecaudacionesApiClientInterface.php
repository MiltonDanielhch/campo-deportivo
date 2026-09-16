<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCobroDTO;
use App\DTOs\WebhookPayloadDTO;
use Illuminate\Http\Request;

/**
 * Interfaz para TESTABILIDAD, no para polimorfismo en runtime:
 * hay un solo sistema externo (el Core). Existe únicamente para
 * poder inyectar el cliente simulado en local/testing sin tocar
 * el resto del sistema (ver roadmap Fase 4.2).
 */
interface RecaudacionesApiClientInterface
{
    /**
     * @throws \App\Exceptions\RecaudacionesApiException si no se puede cobrar
     */
    public function solicitarCobro(SolicitudCobroDTO $datos): RespuestaCobroDTO;

    /**
     * Valida que un webhook entrante realmente viene del Core.
     * Lo no negociable: el webhook rechaza con 401 cualquier llamada
     * que no pase esta verificación.
     */
    public function verificarFirma(Request $request): bool;

    /**
     * Convierte el request entrante en un DTO con las referencias,
     * el monto confirmado (si el Core lo incluye) y el payload crudo.
     */
    public function parsearWebhook(Request $request): WebhookPayloadDTO;
}
