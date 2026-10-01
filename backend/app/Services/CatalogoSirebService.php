<?php

namespace App\Services;

use App\Exceptions\RecaudacionesApiException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\CampoDeportivo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve tarifa_id de SIREB para un campo + hora dados.
 *
 * Cachea el catálogo completo 10 minutos (TTL corto porque los
 * precios cambian poco pero queremos frescura razonable).
 *
 * El discriminador diurno/nocturno se basa en la etiqueta de la
 * tarifa (no en categorías, que SIREB v1 no usa).
 */
class CatalogoSirebService
{
    private const CACHE_KEY = 'sireb:catalogo';
    private const CACHE_TTL_SEGUNDOS = 600;

    public function __construct(
        private RecaudacionesApiClientInterface $client,
    ) {
    }

    /**
     * Resuelve el tarifa_id de SIREB para un campo en una hora específica.
     *
     * @throws \RuntimeException si el campo no tiene servicio_sireb_id mapeado
     * @throws \RuntimeException si no existe la tarifa diurna/nocturna
     */
    public function resolverTarifaId(CampoDeportivo $campo, string $horaInicio): string
    {
        if (empty($campo->servicio_sireb_id)) {
            throw new \RuntimeException(
                "Campo {$campo->codigo} no tiene servicio_sireb_id mapeado. " .
                "Ejecutá 'php artisan sireb:mapear-campos' para cargar el mapeo."
            );
        }

        $etiqueta = $this->resolverEtiqueta($campo, $horaInicio);
        $catalogo = $this->obtenerCatalogo();

        $servicio = collect($catalogo)->firstWhere('id', $campo->servicio_sireb_id);
        if (! $servicio) {
            // Forzar refresco de cache y reintentar una vez
            Cache::forget(self::CACHE_KEY);
            $catalogo = $this->obtenerCatalogo(true);
            $servicio = collect($catalogo)->firstWhere('id', $campo->servicio_sireb_id);

            if (! $servicio) {
                throw new \RuntimeException(
                    "Servicio SIREB {$campo->servicio_sireb_id} no existe en el catálogo."
                );
            }
        }

        $tarifa = collect($servicio['tarifas'] ?? [])
            ->first(fn (array $t) => strcasecmp($t['etiqueta'] ?? '', $etiqueta) === 0);

        if (! $tarifa) {
            throw new \RuntimeException(
                "No se encontró tarifa '{$etiqueta}' para el servicio {$servicio['codigo']}. " .
                "Verificá el tarifario vigente en SIREB."
            );
        }

        return $tarifa['id'];
    }

    /**
     * Obtiene el monto de una tarifa ya resuelta (para validación local).
     */
    public function obtenerMontoTarifa(string $tarifaId): float
    {
        $catalogo = $this->obtenerCatalogo();

        foreach ($catalogo as $servicio) {
            foreach ($servicio['tarifas'] ?? [] as $tarifa) {
                if ($tarifa['id'] === $tarifaId) {
                    return (float) $tarifa['monto'];
                }
            }
        }

        throw new \RuntimeException("Tarifa {$tarifaId} no encontrada en el catálogo.");
    }

    private function resolverEtiqueta(CampoDeportivo $campo, string $horaInicio): string
    {
        $inicioNoche = $campo->hora_inicio_noche ?? '18:00:00';

        return $this->aSegundos($horaInicio) < $this->aSegundos($inicioNoche)
            ? 'Diurno'
            : 'Nocturno';
    }

    private function obtenerCatalogo(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SEGUNDOS, function () {
            try {
                return $this->client->listarCatalogo(pagina: 1, porPagina: 100);
            } catch (RecaudacionesApiException $e) {
                Log::channel('sireb')->error('Error al obtener catálogo', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    private function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));
        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }
}
