<?php

namespace App\Services;

use App\Exceptions\RecaudacionesApiException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\CampoDeportivo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\TarifaCampo;

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
    private const CACHE_TS_KEY = 'sireb:catalogo:actualizado_en';
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
                "Vinculalo desde el panel o ejecutá el mapeo correspondiente."
            );
        }

        $tarifa = $this->tarifaEspejoVigente($campo, $horaInicio);

        if (! $tarifa || ! $tarifa->tarifa_sireb_id) {
            throw new \RuntimeException(
                "No hay tarifa SIREB vigente espejada para {$campo->codigo} a las {$horaInicio}. " .
                "Ejecutá php artisan sireb:sincronizar-tarifas."
            );
        }

        return $tarifa->tarifa_sireb_id;
    }

    /**
     * Obtiene el monto de una tarifa ya resuelta (para validación local).
     */
    public function obtenerMontoTarifa(string $tarifaId): float
    {
        $tarifa = TarifaCampo::query()
            ->where('tarifa_sireb_id', $tarifaId)
            ->whereNull('vigente_hasta')
            ->where('liquidable', true)
            ->where('tarifario_estado', 'vigente')
            ->first();

        if (! $tarifa) {
            throw new \RuntimeException(
                "Tarifa SIREB {$tarifaId} no está espejada como vigente/liquidable. " .
                "Ejecutá php artisan sireb:sincronizar-tarifas."
            );
        }

        return (float) $tarifa->precio_por_hora;
    }

    private function tarifaEspejoVigente(CampoDeportivo $campo, string $horaInicio): ?TarifaCampo
    {
        $activas = TarifaCampo::paraGrillaHoraria()
            ->where('campo_id', $campo->id)
            ->get();

        if ($activas->isEmpty()) {
            return null;
        }

        $unica = $activas->firstWhere('clasificacion', 'unica');
        if ($unica) {
            return $unica;
        }

        $inicioNoche = $this->aSegundos($campo->hora_inicio_noche ?? '18:00:00');
        $tipo = $this->aSegundos($horaInicio) < $inicioNoche
            ? 'diurna'
            : 'nocturna';

        $coleccion = $activas->where('clasificacion', $tipo);

        if ($coleccion->count() > 1) {
            throw new \RuntimeException(
                "Tarifa espejada ambigua para {$campo->codigo}: múltiples activas de tipo {$tipo}."
            );
        }

        return $coleccion->first();
    }

    /**
     * SIREB pagina su catálogo (pagina + por_pagina). El bug clásico es
     * romper cuando la página viene "incompleta" (count < por_pagina),
     * asumiendo que no hay más. Falso: muchas APIs tienen page size
     * propio (5, 20) e ignoran por_pagina grande. Resultado: perdemos
     * silenciosamente los servicios que viven en página > 1.
     *
     * Estrategia correcta: pedir página tras página hasta que venga
     * VACÍA (data = []). Dedupe por id (sirve de salvaguarda extra si
     * SIREB repite páginas en una API bugueada). MAX_PAGINAS evita
     * loop infinito: 50 × 100 = 5000 servicios, más que suficiente.
     */
    private const MAX_PAGINAS = 50;

    private function obtenerCatalogo(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::CACHE_TS_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SEGUNDOS, function () {
            try {
                $catalogo = [];
                $idsVistos = [];
                $pagina = 1;

                while ($pagina <= self::MAX_PAGINAS) {
                    $lote = $this->client->listarCatalogo(pagina: $pagina, porPagina: 100);

                    if (empty($lote)) {
                        // Página vacía = fin del catálogo.
                        break;
                    }

                    foreach ($lote as $servicio) {
                        $id = $servicio['id'] ?? null;
                        if ($id === null) {
                            // Sin id no podemos deduplicar: lo aceptamos igual.
                            $catalogo[] = $servicio;
                            continue;
                        }
                        if (isset($idsVistos[$id])) {
                            continue;
                        }
                        $idsVistos[$id] = true;
                        $catalogo[] = $servicio;
                    }

                    $pagina++;
                }

                Log::channel('sireb')->info('Catálogo SIREB espejado', [
                    'paginas_leidas' => $pagina,
                    'total_servicios' => count($catalogo),
                ]);

                Cache::put(
                    self::CACHE_TS_KEY,
                    now()->toIso8601String(),
                    self::CACHE_TTL_SEGUNDOS
                );

                return $catalogo;
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

    /**
     * Devuelve el catálogo SIREB cacheado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogoCacheado(): array
    {
        return $this->obtenerCatalogo();
    }

    /**
     * Ignora la caché y vuelve a pedir el catálogo a SIREB.
     *
     * Lo usan la sincronización de campos y el botón "Sincronizar" del panel,
     * donde el operador espera ver el estado real de /panel/servicios.
     *
     * @return array<int, array<string, mixed>>
     */
    public function refrescarCatalogo(): array
    {
        return $this->obtenerCatalogo(true);
    }

    /**
     * Busca un servicio SIREB por su UUID interno.
     *
     * @return array<string, mixed>|null
     */
    public function servicioPorId(?string $servicioId): ?array
    {
        if (blank($servicioId)) {
            return null;
        }

        return collect($this->obtenerCatalogo())
            ->firstWhere('id', $servicioId);
    }

    /**
     * Fecha/hora de la última actualización exitosa del catálogo cacheado.
     */
    public function ultimaActualizacion(): ?string
    {
        return Cache::get(self::CACHE_TS_KEY);
    }
}
