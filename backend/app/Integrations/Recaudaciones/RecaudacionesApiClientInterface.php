<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\EstadoCobroDTO;
use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCobroDTO;
use App\DTOs\WebhookPayloadDTO;
use Illuminate\Http\Request;

interface RecaudacionesApiClientInterface
{
    public function solicitarCobro(SolicitudCobroDTO $datos): RespuestaCobroDTO;

    public function verificarFirma(Request $request): bool;

    public function parsearWebhook(Request $request): WebhookPayloadDTO;

    /**
     * Consulta el estado de un cobro al Core (polling, HU-D5).
     * Devuelve null si la referencia no existe en el Core.
     */
    public function consultarEstado(string $referenciaRecaudaciones): ?EstadoCobroDTO;
}
