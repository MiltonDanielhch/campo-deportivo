<?php

namespace App\Services;

use App\Models\CampoDeportivo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Presenta campos públicos enriquecidos con el catálogo de SIREB/Paitití.
 *
 * Regla:
 * - Si SIREB responde, los precios vienen de SIREB.
 * - Si SIREB falla, se usa tarifas_campo local como fallback.
 * - Se mantiene compatibilidad con el frontend actual exponiendo:
 *   tarifas.diurna y tarifas.nocturna.
 * - Se agrega bloque sireb con detalle completo.
 */
class CampoPublicoSirebPresenter
{
    public function __construct(
        private CatalogoSirebService $catalogoSireb,
    ) {
    }

    /**
     * @param Collection<int, CampoDeportivo> $campos
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function index(Collection $campos): array
    {
        [$servicios, $fuente, $aviso] = $this->obtenerServiciosSireb();

        return [
            'data' => $campos
                ->map(fn (CampoDeportivo $campo) => $this->presentar($campo, $servicios, $fuente))
                ->values()
                ->all(),
            'meta' => $this->meta($fuente, $aviso),
        ];
    }

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function show(CampoDeportivo $campo): array
    {
        [$servicios, $fuente, $aviso] = $this->obtenerServiciosSireb();

        return [
            'data' => $this->presentar($campo, $servicios, $fuente),
            'meta' => $this->meta($fuente, $aviso),
        ];
    }

    /**
     * @return array{0: Collection<string, array<string, mixed>>|null, 1: string, 2: string|null}
     */
    private function obtenerServiciosSireb(): array
    {
        try {
            $catalogo = $this->catalogoSireb->catalogoCacheado();

            $servicios = collect($catalogo)
                ->filter(fn ($servicio) => isset($servicio['id']))
                ->keyBy(fn ($servicio) => (string) $servicio['id']);

            return [$servicios, 'SIREB', null];
        } catch (\Throwable $e) {
            Log::channel('sireb')->warning(
                'No se pudo obtener catálogo SIREB para catálogo público; usando fallback local.',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return [
                null,
                'LOCAL_FALLBACK',
                'No se pudo consultar el catálogo de recaudaciones. Se muestran precios locales de respaldo.',
            ];
        }
    }

    private function meta(string $fuente, ?string $aviso): array
    {
        return [
            'fuente_precios' => $fuente,
            'sincronizado_en' => $this->catalogoSireb->ultimaActualizacion(),
            'aviso' => $aviso,
        ];
    }

    /**
     * @param Collection<string, array<string, mixed>>|null $servicios
     * @return array<string, mixed>
     */
    private function presentar(
        CampoDeportivo $campo,
        ?Collection $servicios,
        string $fuente
    ): array {
        $base = $this->base($campo);

        if ($fuente === 'SIREB' && $servicios !== null) {
            $servicio = $servicios->get((string) $campo->servicio_sireb_id);

            if (is_array($servicio)) {
                return array_merge($base, $this->desdeSireb($servicio));
            }

            return array_merge($base, $this->noVinculado());
        }

        return array_merge($base, $this->desdeLocal($campo));
    }

    /**
     * @return array<string, mixed>
     */
    private function base(CampoDeportivo $campo): array
    {
        return [
            'id' => $campo->id,
            'nombre' => $campo->nombre,
            'tipo_campo' => $campo->relationLoaded('tipoCampo') && $campo->tipoCampo
                ? [
                    'id' => $campo->tipoCampo->id,
                    'nombre' => $campo->tipoCampo->nombre,
                ]
                : null,
            'direccion' => $campo->direccion,
            'imagen_url' => $campo->imagen_url,
            'latitud' => (float) $campo->latitud,
            'longitud' => (float) $campo->longitud,
            'estado' => $campo->estado,
            'hora_inicio_noche' => $campo->hora_inicio_noche,
            'servicio_sireb_id' => $campo->servicio_sireb_id,
            'horarios_atencion' => $campo->relationLoaded('horariosAtencion')
                ? $campo->horariosAtencion
                    ->sortBy('dia_semana')
                    ->map(fn ($h) => [
                        'dia_semana' => $h->dia_semana,
                        'hora_apertura' => substr((string) $h->hora_apertura, 0, 5),
                        'hora_cierre' => substr((string) $h->hora_cierre, 0, 5),
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $servicio
     * @return array<string, mixed>
     */
    private function desdeSireb(array $servicio): array
    {
        $tarifas = $this->normalizarTarifas($servicio['tarifas'] ?? []);

        $diurna = collect($tarifas)->firstWhere('tipo', 'diurna');
        $nocturna = collect($tarifas)->firstWhere('tipo', 'nocturna');

        $reservable = $this->esReservable($servicio, $tarifas);

        $precios = collect($tarifas)
            ->pluck('precio')
            ->filter()
            ->values();

        $precioMin = $precios->isNotEmpty() ? (float) $precios->min() : null;
        $precioMax = $precios->isNotEmpty() ? (float) $precios->max() : null;

        return [
            // Compatibilidad con frontend actual.
            'tarifas' => [
                'diurna' => $diurna ? $this->tarifaPublica($diurna) : null,
                'nocturna' => $nocturna ? $this->tarifaPublica($nocturna) : null,
            ],

            // Información rica desde SIREB/Paitití.
            'sireb' => [
                'id' => $servicio['id'] ?? null,
                'codigo' => $servicio['codigo'] ?? null,
                'nombre' => $servicio['nombre'] ?? null,
                'rubro' => $servicio['rubro'] ?? null,
                'estado' => $servicio['estado'] ?? null,
                'tarifario' => $servicio['tarifario'] ?? ($reservable ? 'liquidable' : 'sin_tarifa'),
                'unidad_medida' => $servicio['unidad_medida'] ?? 'hora',
                'precio_min' => $precioMin,
                'precio_max' => $precioMax,
                'tarifas' => $tarifas,
                'fuente' => 'SIREB',
            ],

            'reservable_online' => $reservable,
            'mensaje_no_reservable' => $reservable
                ? null
                : $this->motivoNoReservable($servicio, $tarifas),
            'fuente_precios' => 'SIREB',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function desdeLocal(CampoDeportivo $campo): array
    {
        $tarifasLocales = $campo->relationLoaded('tarifas')
            ? $campo->tarifas
            : collect();

        $diurna = $tarifasLocales->firstWhere('tipo_tarifa', 'diurna');
        $nocturna = $tarifasLocales->firstWhere('tipo_tarifa', 'nocturna');

        $reservable = $diurna !== null || $nocturna !== null;

        return [
            'tarifas' => [
                'diurna' => $diurna ? [
                    'precio_por_hora' => (float) $diurna->precio_por_hora,
                    'vigente_desde' => $diurna->vigente_desde?->toIso8601String(),
                    'etiqueta' => 'Diurno',
                    'tipo' => 'diurna',
                    'sireb_tarifa_id' => null,
                ] : null,
                'nocturna' => $nocturna ? [
                    'precio_por_hora' => (float) $nocturna->precio_por_hora,
                    'vigente_desde' => $nocturna->vigente_desde?->toIso8601String(),
                    'etiqueta' => 'Nocturno',
                    'tipo' => 'nocturna',
                    'sireb_tarifa_id' => null,
                ] : null,
            ],
            'sireb' => null,
            'reservable_online' => $reservable,
            'mensaje_no_reservable' => $reservable
                ? null
                : 'Este campo no tiene tarifa vigente registrada.',
            'fuente_precios' => 'LOCAL_FALLBACK',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noVinculado(): array
    {
        return [
            'tarifas' => [
                'diurna' => null,
                'nocturna' => null,
            ],
            'sireb' => null,
            'reservable_online' => false,
            'mensaje_no_reservable' => 'Este campo aún no está vinculado al catálogo de recaudaciones.',
            'fuente_precios' => 'NO_VINCULADO',
        ];
    }

    /**
     * @param array<int, mixed> $tarifas
     * @return array<int, array<string, mixed>>
     */
    private function normalizarTarifas(array $tarifas): array
    {
        return collect($tarifas)
            ->map(function (array $tarifa) {
                $etiqueta = (string) ($tarifa['etiqueta'] ?? '');

                $precio = null;

                if (isset($tarifa['monto'])) {
                    $precio = (float) $tarifa['monto'];
                } elseif (isset($tarifa['precio'])) {
                    $precio = (float) $tarifa['precio'];
                } elseif (isset($tarifa['precio_por_hora'])) {
                    $precio = (float) $tarifa['precio_por_hora'];
                }

                return [
                    'id' => $tarifa['id'] ?? null,
                    'tipo' => $this->tipoDesdeEtiqueta($etiqueta),
                    'etiqueta' => $etiqueta !== '' ? $etiqueta : 'Tarifa',
                    'precio' => $precio,
                    'unidad_medida' => $tarifa['unidad_medida'] ?? null,
                    'vigente_desde' => $tarifa['vigente_desde'] ?? null,
                ];
            })
            ->filter(fn (array $tarifa) => $tarifa['precio'] !== null)
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $tarifa
     * @return array<string, mixed>
     */
    private function tarifaPublica(array $tarifa): array
    {
        return [
            'precio_por_hora' => (float) $tarifa['precio'],
            'vigente_desde' => $tarifa['vigente_desde'] ?? now()->toIso8601String(),
            'etiqueta' => $tarifa['etiqueta'],
            'tipo' => $tarifa['tipo'],
            'sireb_tarifa_id' => $tarifa['id'],
        ];
    }

    /**
     * @param array<string, mixed> $servicio
     * @param array<int, array<string, mixed>> $tarifas
     */
    private function esReservable(array $servicio, array $tarifas): bool
    {
        $estado = $servicio['estado'] ?? null;

        if ($estado !== null && ! in_array($estado, ['activo', 'ACTIVO'], true)) {
            return false;
        }

        $tarifario = $servicio['tarifario'] ?? null;

        if ($tarifario === 'sin_tarifa') {
            return false;
        }

        return count($tarifas) > 0;
    }

    /**
     * @param array<string, mixed> $servicio
     * @param array<int, array<string, mixed>> $tarifas
     */
    private function motivoNoReservable(array $servicio, array $tarifas): string
    {
        $estado = $servicio['estado'] ?? null;

        if ($estado !== null && ! in_array($estado, ['activo', 'ACTIVO'], true)) {
            return 'El servicio no está activo en recaudaciones.';
        }

        $tarifario = $servicio['tarifario'] ?? null;

        if ($tarifario === 'sin_tarifa' || count($tarifas) === 0) {
            return 'Este servicio no tiene tarifa liquidable. Consultá precio en ventanilla.';
        }

        return 'Este servicio no puede reservarse online en este momento.';
    }

    private function tipoDesdeEtiqueta(string $etiqueta): ?string
    {
        $e = mb_strtolower($etiqueta);

        return match (true) {
            str_contains($e, 'nocturn') => 'nocturna',
            str_contains($e, 'diurn') => 'diurna',
            default => null,
        };
    }
}
