<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\EstadoCobroDTO;
use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCobroDTO;
use App\DTOs\WebhookPayloadDTO;
use App\Exceptions\RecaudacionesApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP real para SIREB (Gateway de Recaudaciones GAD Beni).
 *
 * Autenticación: OAuth2 client_credentials contra Ibare (Authorization Server).
 * El token JWT se cachea en Redis con TTL = expires_in - 60 segundos.
 *
 * Contrato v1 de SIREB (2026-09-29):
 * - NO hay webhooks (polling vía GET /liquidaciones/{codigoPublico})
 * - NO hay QR/pago electrónico (solo pago manual con boleta bancaria)
 * - Catálogo consultable vía GET /catalogo/servicios
 * - Idempotency-Key obligatorio en POST /liquidaciones
 */
class RecaudacionesApiClient implements RecaudacionesApiClientInterface
{
    private readonly string $sirebBaseUrl;
    private readonly string $tokenUrl;
    private readonly string $clientId;
    private readonly string $clientSecret;
    private readonly int $timeoutSeconds;

    public function __construct()
    {
        $this->sirebBaseUrl = rtrim((string) config('services.recaudaciones.url'), '/');
        $this->tokenUrl = (string) config('services.recaudaciones.oauth.token_url');
        $this->clientId = (string) config('services.recaudaciones.oauth.client_id');
        $this->clientSecret = (string) config('services.recaudaciones.oauth.client_secret');
        $this->timeoutSeconds = (int) config('services.recaudaciones.timeout_seconds', 10);
    }

    /**
     * Obtiene un token JWT de Ibare vía OAuth2 client_credentials.
     * Cachea el token en Redis con clave 'sireb:access_token'.
     *
     * @throws RecaudacionesApiException si Ibare no responde o responde error
     */
    /**
     * Versión de diagnóstico: prueba 3 variantes de OAuth2 para descubrir
     * cuál acepta Ibare. Se reemplazará por la versión limpia una vez identificada.
     */
    private function obtenerToken(): string
    {
        $cacheKey = 'sireb:access_token';

        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        if ($this->tokenUrl === '') {
            throw RecaudacionesApiException::respuestaInvalida(
                0,
                'Falta configurar SIREB_TOKEN_URL en .env'
            );
        }
        $tokenUrl = $this->tokenUrl;

        Log::channel('sireb')->info('=== INICIO diagnóstico OAuth2 ===', [
            'token_url' => $tokenUrl,
            'client_id' => $this->clientId,
        ]);

        // ─── Variante 1: form-urlencoded estándar (la que teníamos) ───
        Log::channel('sireb')->info('Variante 1: form-urlencoded estándar');
        $response1 = Http::timeout($this->timeoutSeconds)
            ->asForm()
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);
        Log::channel('sireb')->info('Respuesta Variante 1', [
            'status' => $response1->status(),
            'body' => $response1->body(),
        ]);

        // ─── Variante 2: con scope=* (común en Laravel Passport) ───
        Log::channel('sireb')->info('Variante 2: form-urlencoded con scope=*');
        $response2 = Http::timeout($this->timeoutSeconds)
            ->asForm()
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => '*',
            ]);
        Log::channel('sireb')->info('Respuesta Variante 2', [
            'status' => $response2->status(),
            'body' => $response2->body(),
        ]);

        // ─── Variante 3: Basic Auth (credentials en header) ───
        Log::channel('sireb')->info('Variante 3: Basic Auth');
        $response3 = Http::timeout($this->timeoutSeconds)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
            ]);
        Log::channel('sireb')->info('Respuesta Variante 3', [
            'status' => $response3->status(),
            'body' => $response3->body(),
        ]);

        // Tomar la primera que funcione
        $response = null;
        $varianteGanadora = null;
        foreach ([$response1, $response2, $response3] as $i => $r) {
            if ($r->successful()) {
                $response = $r;
                $varianteGanadora = $i + 1;
                break;
            }
        }

        if (!$response || $response->failed()) {
            Log::channel('sireb')->error('=== FIN diagnóstico: ninguna variante funcionó ===');
            throw RecaudacionesApiException::respuestaInvalida(
                $response?->status() ?? 0,
                'Ninguna variante OAuth2 funcionó. Revisar storage/logs/sireb.log'
            );
        }

        Log::channel('sireb')->info("=== FIN diagnóstico: Variante {$varianteGanadora} funcionó ===");

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        if (!$accessToken) {
            throw RecaudacionesApiException::respuestaInvalida(
                $response->status(),
                'Ibare no devolvió access_token'
            );
        }

        $ttl = max(60, $expiresIn - 60);
        Cache::put($cacheKey, $accessToken, $ttl);

        return $accessToken;
    }

    /**
     * Verifica si hay un token válido en cache.
     */
    private function tokenValido(): bool
    {
        return Cache::has('sireb:access_token');
    }

    /**
     * Invalida el token cacheado (útil cuando SIREB responde TOKEN_INVALIDO).
     */
    private function invalidarToken(): void
    {
        Cache::forget('sireb:access_token');
        Log::channel('sireb')->warning('Token invalidado manualmente');
    }

    /**
     * Realiza una petición HTTP a SIREB con token Bearer.
     * Si SIREB responde TOKEN_INVALIDO (401), refresca el token y reintenta UNA vez.
     *
     * @throws RecaudacionesApiException
     */
    private function request(
        string $method,
        string $path,
        array $payload = [],
        array $headers = []
    ): array {
        $token = $this->obtenerToken();

        try {
            $response = Http::baseUrl($this->sirebBaseUrl)
                ->withToken($token)
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->connectTimeout(min(5, $this->timeoutSeconds))
                ->retry(3, 100, function ($exception, $request) {
                    // Solo reintentar en errores de conexión o 5xx
                    return $exception instanceof ConnectionException
                        || ($request->response && $request->response->status() >= 500);
                })
                ->{$method}($path, $payload ?: null);
        } catch (ConnectionException $e) {
            Log::channel('sireb')->error('Error de conexión con SIREB', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw RecaudacionesApiException::conexionFallida($e->getMessage());
        }

        // Si SIREB responde TOKEN_INVALIDO, refrescar y reintentar UNA vez
        if ($response->status() === 401) {
            $errorData = $response->json();
            $codigo = $errorData['codigo'] ?? '';

            if ($codigo === 'TOKEN_INVALIDO') {
                Log::channel('sireb')->warning('Token inválido, refrescando y reintentando', [
                    'method' => $method,
                    'path' => $path,
                ]);

                $this->invalidarToken();
                $token = $this->obtenerToken();

                $response = Http::baseUrl($this->sirebBaseUrl)
                    ->withToken($token)
                    ->withHeaders($headers)
                    ->acceptJson()
                    ->timeout($this->timeoutSeconds)
                    ->{$method}($path, $payload ?: null);
            }
        }

        Log::channel('sireb')->info('Llamada a SIREB', [
            'method' => $method,
            'path' => $path,
            'status' => $response->status(),
            'duration_ms' => $response->handlerStats()['total_time'] ?? null,
        ]);

        if ($response->failed()) {
            $errorData = $response->json();
            $codigo = $errorData['codigo'] ?? 'ERROR_DESCONOCIDO';
            $mensaje = $errorData['mensaje'] ?? $response->body();

            Log::channel('sireb')->error('SIREB respondió error', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'codigo' => $codigo,
                'mensaje' => $mensaje,
            ]);

            throw RecaudacionesApiException::respuestaInvalida(
                $response->status(),
                "[{$codigo}] {$mensaje}"
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Busca un cliente por CI/NIT en SIREB.
     * Devuelve null si no existe (404 o array vacío).
     */
    public function buscarCliente(?string $ciNit): ?array
    {
        if (blank($ciNit)) {
            return null;
        }
        try {
            $response = $this->request('get', '/api/v1/clientes', [
                'ci_nit' => $ciNit,
            ]);

            $clientes = $response['data'] ?? [];
            return $clientes[0] ?? null;
        } catch (RecaudacionesApiException $e) {
            // Si SIREB responde 404 o 422 (falta ci_nit), devolver null
            if ($e->coreStatus === 404 || $e->coreStatus === 422) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Registra un cliente nuevo en SIREB.
     *
     * @throws RecaudacionesApiException si el CI/NIT ya existe (422)
     */
    public function registrarCliente(array $datos): array
    {
        $response = $this->request('post', '/api/v1/clientes', $datos);
        return $response['data'] ?? [];
    }

    /**
     * Lista los servicios liquidables del catálogo de SIREB.
     * Cada servicio incluye sus tarifas vigentes.
     */
    public function listarCatalogo(int $pagina = 1, int $porPagina = 100): array
    {
        $response = $this->request('get', '/api/v1/catalogo/servicios', [
            'pagina' => $pagina,
            'por_pagina' => min(100, $porPagina),
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Crea una liquidación en SIREB.
     *
     * @param array $items Array de items con tarifa_id y cantidad
     * @param string $clienteId UUID del cliente en SIREB
     * @param string $idempotencyKey Clave única para idempotencia
     * @param string $referenciaExterna Nuestro codigo_seguimiento
     * @param string|null $sucursalId UUID de la sucursal (opcional)
     *
     * @throws RecaudacionesApiException
     */
    public function crearLiquidacion(
        array $items,
        string $clienteId,
        string $idempotencyKey,
        string $referenciaExterna,
        ?string $sucursalId = null
    ): array {
        $payload = [
            'cliente_id' => $clienteId,
            'items' => $items,
            'referencia_externa' => $referenciaExterna,
        ];

        if ($sucursalId) {
            $payload['sucursal_id'] = $sucursalId;
        }

        $response = $this->request('post', '/api/v1/liquidaciones', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Consulta el estado de una liquidación por su código público.
     * Este endpoint es PÚBLICO (no requiere auth), pero lo hacemos con token igual.
     */
    public function consultarLiquidacionPorCodigo(string $codigoPublico): ?array
    {
        try {
            $response = $this->request('get', "/api/v1/liquidaciones/{$codigoPublico}");
            return $response['data'] ?? null;
        } catch (RecaudacionesApiException $e) {
            // 404 = código no existe o mal formado
            if ($e->coreStatus === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Consulta el detalle de una liquidación por su UUID (requiere auth).
     */
    public function consultarLiquidacionDetalle(string $liquidacionId): ?array
    {
        try {
            $response = $this->request('get', "/api/v1/liquidaciones/{$liquidacionId}");
            return $response['data'] ?? null;
        } catch (RecaudacionesApiException $e) {
            if ($e->coreStatus === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Anula una liquidación pendiente sin pago activo.
     *
     * @throws RecaudacionesApiException si la liquidación ya tiene pago (422 LIQUIDACION_NO_ANULABLE)
     */
    public function anularLiquidacion(string $liquidacionId, string $motivo): array
    {
        $response = $this->request('patch', "/api/v1/liquidaciones/{$liquidacionId}/anular", [
            'motivo' => $motivo,
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Registra un pago manual en SIREB (número de boleta + entidad bancaria).
     */
    public function registrarPagoManual(
        string $liquidacionId,
        string $numeroBoleta,
        string $entidadBancaria
    ): array {
        $response = $this->request('post', "/api/v1/liquidaciones/{$liquidacionId}/pago-manual", [
            'numero_boleta' => $numeroBoleta,
            'entidad_bancaria' => $entidadBancaria,
        ]);

        return $response['data'] ?? [];
    }

    // ─── Métodos de la interfaz (compatibilidad con código existente) ───

    /**
     * Método de la interfaz original.
     * TODO: adaptar al contrato real de SIREB cuando tengamos servicios cargados.
     * Por ahora lanza excepción para forzar el uso de los métodos nuevos.
     */
    public function solicitarCobro(SolicitudCobroDTO $datos): RespuestaCobroDTO
    {
        throw new \RuntimeException(
            'solicitarCobro() no está implementado en el cliente real. ' .
            'Usar crearLiquidacion() directamente.'
        );
    }

    /**
     * Consulta el estado de un cobro (polling).
     * Adaptado al contrato real de SIREB: consulta por codigo_publico.
     */
    public function consultarEstado(string $referenciaRecaudaciones): ?EstadoCobroDTO
    {
        $liquidacion = $this->consultarLiquidacionPorCodigo($referenciaRecaudaciones);

        if (!$liquidacion) {
            return null;
        }

        $estado = $liquidacion['estado'] ?? 'pendiente';
        $pagado = $estado === 'pagada';
        $montoConfirmado = $pagado ? (float) ($liquidacion['monto'] ?? 0) : null;

        return new EstadoCobroDTO(
            pagado: $pagado,
            montoConfirmado: $montoConfirmado,
        );
    }

    /**
     * Verificación de firma del webhook.
     * TODO: SIREB v1 NO tiene webhooks. Este método queda como stub.
     * Cuando SIREB implemente webhooks, ajustar el mecanismo de firma.
     */
    public function verificarFirma(Request $request): bool
    {
        // SIREB v1 no manda webhooks, así que esto siempre devuelve false
        Log::channel('sireb')->warning(
            'verificarFirma() llamado pero SIREB v1 no soporta webhooks'
        );
        return false;
    }

    /**
     * Parseo del payload del webhook.
     * TODO: SIREB v1 NO tiene webhooks. Este método queda como stub.
     */
    public function parsearWebhook(Request $request): WebhookPayloadDTO
    {
        throw new \RuntimeException(
            'parsearWebhook() no está implementado: SIREB v1 no soporta webhooks'
        );
    }
}
