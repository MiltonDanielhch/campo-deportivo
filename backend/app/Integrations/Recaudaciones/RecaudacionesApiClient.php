<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCobroDTO;
use App\Exceptions\RecaudacionesApiException;
use App\Models\ParametroSistema;
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
class RecaudacionesApiClient implements RecaudacionesApiClientInterface
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
     * mostrar el QR / checkout al ciudadano.
     *
     * TODO: confirmar contrato real (ruta y payload) con el equipo del Core.
     */
    public function solicitarCobro(SolicitudCobroDTO $datos): RespuestaCobroDTO
    {
        $data = $this->request('post', '/cobros', [
            'referencia_externa' => $datos->referenciaExterna,
            'monto' => $datos->monto,
            'solicitante' => [
                'nombre' => $datos->nombrePagador,
                'telefono' => $datos->telefonoPagador,
                'ci_nit' => $datos->ciNitPagador,
            ],
            'descripcion' => $datos->descripcion,
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
        $timeout = $this->timeoutSegundos();

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withToken($this->token)
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout(min(5, $timeout))
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

    /** Lee el parámetro de timeout (default 8s si no está sembrado). */
    private function timeoutSegundos(): int
    {
        $valor = ParametroSistema::where('clave', 'recaudaciones_timeout_segundos')->value('valor');

        return (int) ($valor ?? 8);
    }
}
