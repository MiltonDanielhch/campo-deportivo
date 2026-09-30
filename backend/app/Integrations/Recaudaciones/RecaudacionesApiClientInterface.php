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

        // ─── Métodos específicos de SIREB v1 ───

    /**
     * Busca un cliente por CI/NIT en SIREB.
     * Devuelve null si no existe.
     */
    public function buscarCliente(string $ciNit): ?array;

    /**
     * Registra un cliente nuevo en SIREB.
     */
    public function registrarCliente(array $datos): array;

    /**
     * Lista los servicios liquidables del catálogo de SIREB.
     */
    public function listarCatalogo(int $pagina = 1, int $porPagina = 100): array;

    /**
     * Crea una liquidación en SIREB con Idempotency-Key.
     */
    public function crearLiquidacion(
        array $items,
        string $clienteId,
        string $idempotencyKey,
        string $referenciaExterna,
        ?string $sucursalId = null
    ): array;

    /**
     * Consulta el estado de una liquidación por su código público.
     */
    public function consultarLiquidacionPorCodigo(string $codigoPublico): ?array;

    /**
     * Consulta el detalle de una liquidación por su UUID.
     */
    public function consultarLiquidacionDetalle(string $liquidacionId): ?array;

    /**
     * Anula una liquidación pendiente sin pago activo.
     */
    public function anularLiquidacion(string $liquidacionId, string $motivo): array;

    /**
     * Registra un pago manual en SIREB.
     */
    public function registrarPagoManual(
        string $liquidacionId,
        string $numeroBoleta,
        string $entidadBancaria
    ): array;
}
