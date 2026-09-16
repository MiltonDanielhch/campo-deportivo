<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCobroDTO;

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
}
