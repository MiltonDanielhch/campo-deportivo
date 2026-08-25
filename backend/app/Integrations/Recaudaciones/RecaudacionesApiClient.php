<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\RespuestaCobroDTO;
use App\Exceptions\RecaudacionesApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Única integración externa del satélite de Canchas: cliente HTTP
 * hacia la API del Core de Recaudaciones (Hub & Spoke, ver ADR-003).
 *
 * Deliberadamente NO implementa patrón Strategy ni Circuit Breaker:
 * existe una sola dependencia externa y su disponibilidad es
 * responsabilidad del Core, no de Canchas.
 */
class RecaudacionesApiClient
{
    private readonly string $baseUrl;

    private readonly string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.recaudaciones.url'), '/');
        $this->token = (string) config('services.recaudaciones.token');
    }

    /**
     * Pide al Core que genere un cobro y devuelve los datos para
     * mostrar el QR al ciudadano.
     *
     * TODO: confirmar contrato real (ruta y payload) con el equipo del Core.
     */
    public function solicitarCobro(array $franjas, array $datosSolicitante): RespuestaCobroDTO
    {
        $data = $this->request('post', '/cobros', [
            'franjas' => $franjas,
            'solicitante' => $datosSolicitante,
        ]);

        return RespuestaCobroDTO::fromArray($data);
    }

    /**
     * Consulta el estado de un cobro ya solicitado al Core.
     * Será consumido por el job de sincronización de confirmación
     * (módulo posterior).
     *
     * TODO: pendiente del contrato real del Core.
     */
    public function consultarEstado(string $solicitudCobroId): string
    {
        $data = $this->request('get', "/cobros/{$solicitudCobroId}");

        return (string) ($data['estado'] ?? 'desconocido');
    }

    /**
     * @throws RecaudacionesApiException
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withToken($this->token)
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->{$method}($path, $payload ?: null);
        } catch (ConnectionException $e) {
            throw RecaudacionesApiException::conexionFallida($e->getMessage());
        }

        if ($response->failed()) {
            throw RecaudacionesApiException::respuestaInvalida(
                $response->status(),
                $response->body(),
            );
        }

        return $response->json() ?? [];
    }
}
