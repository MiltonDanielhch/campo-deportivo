<?php

namespace App\Services;

use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Presenta campos públicos enriquecidos con el catálogo de SIREB/Paitití.
 *
 * [2b] Join deliberado:
 *  - Metadatos del servicio (nombre oficial, normativa, rubro, unidad_medida,
 *    plazo_pago) → catálogo VIVO de SIREB (el espejo no los guarda).
 *  - Tarifas / precios / reservable → ESPEJO tariffs_campo, filtrado por el
 *    scope único paraGrillaHoraria() (liquidable+vigente+clasificada+sin perfil).
 *  Así el display público y el resolvedor de cobro leen la MISMA fuente de
 *  precio (cierra N9-para-display) y piscina/no-clasificadas quedan afuera de
 *  la grilla horaria por construcción (cierra H4 desde el display).
 *
 *  Se mantiene compatibilidad con el frontend actual exponiendo:
 *    tarifas.diurna y tarifas.nocturna (+ tarifas.unica nuevo, back-compatible).
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
        // [2b] eager-load del espejo filtrado: evita N+1 en el listado público.
        $campos->load(['tarifas' => fn ($q) => $q->paraGrillaHoraria()]);

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
        // [2b] load() fuerza la recarga con el scope (sobrescribe cualquier with previo).
        $campo->load(['tarifas' => fn ($q) => $q->paraGrillaHoraria()]);

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

        // [2b] Fuente única de tarifas: el espejo ya cargado por index()/show().
        $tarifasEspejo = $this->tarifasEspejoParaGrilla($campo);

        // Si es piscina VINCULADA (modalidad_reserva = 'turno_por_perfil' + servicio_sireb_id),
        // marcar como reservable independientemente de las tarifas horarias (que no tiene por
        // diseño: sus tarifas son de perfil). [B111-display] la excepción exige vínculo pa' no
        // violar el invariante 862 "reservable ⟺ servicio_sireb_id con tarifa": una piscina
        // desvinculada cae al flujo normal → noVinculado() → reservable=false, coherente con el
        // gate B111-gate que ya no la deja cobrar la tarifa vieja.
        if ($campo->modalidad_reserva?->value === 'turno_por_perfil' && filled($campo->servicio_sireb_id)) {
            return array_merge($base, [
                'tarifas' => [
                    'diurna' => null,
                    'nocturna' => null,
                    'unica' => null,
                ],
                'sireb' => null,
                'reservable_online' => true,
                'mensaje_no_reservable' => null,
                'fuente_precios' => 'SIREB_PERFIL',
            ]);
        }

        if ($fuente === 'SIREB' && $servicios !== null) {
            $servicio = $servicios->get((string) $campo->servicio_sireb_id);

            if (is_array($servicio)) {
                return array_merge($base, $this->desdeSireb($servicio, $tarifasEspejo), [
                    'nombre' => $servicio['nombre'] ?? $campo->nombre,
                    'servicio_sireb_codigo' => $servicio['codigo'] ?? $campo->servicio_sireb_codigo,
                ]);
            }

            return array_merge($base, $this->noVinculado());
        }

        return array_merge($base, $this->desdeLocal($campo, $tarifasEspejo));
    }

    /**
     * @return array<string, mixed>
     */
    private function base(CampoDeportivo $campo): array
    {
        return [
            'id' => $campo->id,
            'nombre_local' => $campo->nombre,
            'nombre' => $campo->nombre,
            'tipo_campo' => $campo->relationLoaded('tipoCampo') && $campo->tipoCampo
                ? [
                    'id' => $campo->tipoCampo->id,
                    'nombre' => $campo->tipoCampo->nombre,
                ]
                : null,
            'direccion' => $campo->direccion,
            'provincia' => $campo->provincia,      // ← F3
            'municipio' => $campo->municipio,      // ← F3
            'imagen_url' => $campo->imagen_url,
            'latitud' => (float) $campo->latitud,
            'longitud' => (float) $campo->longitud,
            'estado' => $campo->estado,
            'hora_inicio_noche' => $campo->hora_inicio_noche,
            'servicio_sireb_id' => $campo->servicio_sireb_id,
            'servicio_sireb_codigo' => $campo->servicio_sireb_codigo,
            'modalidad_reserva' => $campo->modalidad_reserva?->value,
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
     * [2b] Tarifas del espejo ya filtradas por el scope (cargadas en index/show).
     * Fallback a query directa por si un caller no eager-loadió.
     */
    private function tarifasEspejoParaGrilla(CampoDeportivo $campo): Collection
    {
        if ($campo->relationLoaded('tarifas')) {
            return $campo->tarifas->keyBy('clasificacion');
        }

        return TarifaCampo::paraGrillaHoraria()
            ->where('campo_id', $campo->id)
            ->get()
            ->keyBy('clasificacion');
    }

    /**
     * [2b] Metadatos del servicio desde SIREB-vivo; tarifas desde el espejo.
     * @param array<string, mixed> $servicio
     */
    private function desdeSireb(array $servicio, Collection $tarifasEspejo): array
    {
        $diurna = $tarifasEspejo->get('diurna');
        $nocturna = $tarifasEspejo->get('nocturna');
        $unica = $tarifasEspejo->get('unica');

        $reservable = $tarifasEspejo->isNotEmpty();

        $precios = $tarifasEspejo->pluck('precio_por_hora')->filter()->values();
        $precioMin = $precios->isNotEmpty() ? (float) $precios->min() : null;
        $precioMax = $precios->isNotEmpty() ? (float) $precios->max() : null;

        return [
            'tarifas' => [
                'diurna' => $diurna ? $this->tarifaEspejoPublica($diurna) : null,
                'nocturna' => $nocturna ? $this->tarifaEspejoPublica($nocturna) : null,
                'unica' => $unica ? $this->tarifaEspejoPublica($unica) : null,
            ],

            'sireb' => [
                'id' => $servicio['id'] ?? null,
                'codigo' => $servicio['codigo'] ?? null,
                'nombre' => $servicio['nombre'] ?? null,
                'descripcion' => $servicio['descripcion'] ?? null,
                'rubro' => $servicio['rubro'] ?? null,
                'estado' => $servicio['estado'] ?? null,
                'modo_tarifa' => $servicio['modo_tarifa'] ?? null,
                'tarifario' => $this->tarifarioDe($servicio, $tarifasEspejo),
                'unidad_medida' => $servicio['unidad_medida'] ?? 'hora',
                'precio_min' => $precioMin,
                'precio_max' => $precioMax,
                // [2b] shape compatible con normalizarTarifas() (campo 'precio'),
                // pero poblada del espejo, no del string de etiqueta.
                'tarifas' => $tarifasEspejo->map(fn (TarifaCampo $t) => [
                    'id' => $t->tarifa_sireb_id,
                    'tipo' => $t->clasificacion,
                    'etiqueta' => $t->etiqueta_sireb ?? 'Tarifa',
                    'precio' => (float) $t->precio_por_hora,
                    'unidad_medida' => $servicio['unidad_medida'] ?? null,
                    'vigente_desde' => $t->vigente_desde?->toIso8601String(),
                ])->values()->all(),
                'fuente' => 'SIREB',
            ],

            'reservable_online' => $reservable,
            'mensaje_no_reservable' => $reservable
                ? null
                : $this->motivoNoReservable($servicio, $tarifasEspejo),
            'fuente_precios' => 'SIREB',
        ];
    }

    /**
     * [2b] Fallback local: mismas tarifas del espejo (ya filtrado), sin metadatos SIREB.
     */
    private function desdeLocal(CampoDeportivo $campo, Collection $tarifasEspejo): array
    {
        $diurna = $tarifasEspejo->get('diurna');
        $nocturna = $tarifasEspejo->get('nocturna');
        $unica = $tarifasEspejo->get('unica');

        $reservable = $tarifasEspejo->isNotEmpty();

        return [
            'tarifas' => [
                'diurna' => $diurna ? $this->tarifaEspejoPublica($diurna) : null,
                'nocturna' => $nocturna ? $this->tarifaEspejoPublica($nocturna) : null,
                'unica' => $unica ? $this->tarifaEspejoPublica($unica) : null,
            ],
            'sireb' => null,
            'reservable_online' => $reservable,
            'mensaje_no_reservable' => $reservable
                ? null
                : 'Este campo no tiene tarifa vigente espejada. Ejecutá la sincronización desde SIREB.',
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
                'unica' => null,
            ],
            'sireb' => null,
            'reservable_online' => false,
            'mensaje_no_reservable' => 'Este campo aún no está vinculado al catálogo de recaudaciones.',
            'fuente_precios' => 'NO_VINCULADO',
        ];
    }

    /**
     * [2b] Reemplaza tarifaPublica(): lee del model del espejo, no de array normalizado.
     */
    private function tarifaEspejoPublica(TarifaCampo $tarifa): array
    {
        return [
            'precio_por_hora' => (float) $tarifa->precio_por_hora,
            'vigente_desde' => $tarifa->vigente_desde?->toIso8601String(),
            'etiqueta' => $tarifa->etiqueta_sireb ?? 'Tarifa',
            'tipo' => $tarifa->clasificacion,
            'sireb_tarifa_id' => $tarifa->tarifa_sireb_id,
        ];
    }

    /**
     * [2b] Reservable = hay tarifa del espejo ofrecible en grilla horaria.
     * @param array<string, mixed> $servicio
     */
    private function esReservable(array $servicio, Collection $tarifasEspejo): bool
    {
        $estado = $servicio['estado'] ?? null;

        if ($estado !== null && ! in_array($estado, ['activo', 'ACTIVO'], true)) {
            return false;
        }

        if (($servicio['tarifario'] ?? null) === 'sin_tarifa') {
            return false;
        }

        return $tarifasEspejo->isNotEmpty();
    }

    /**
     * [2b] @param array<string, mixed> $servicio
     */
    private function tarifarioDe(array $servicio, Collection $tarifasEspejo): string
    {
        if (($servicio['tarifario'] ?? null) === 'sin_tarifa') {
            return 'sin_tarifa';
        }

        return $tarifasEspejo->isNotEmpty() ? 'liquidable' : 'sin_tarifa';
    }

    /**
     * [2b] @param array<string, mixed> $servicio
     */
    private function motivoNoReservable(array $servicio, Collection $tarifasEspejo): string
    {
        $estado = $servicio['estado'] ?? null;

        if ($estado !== null && ! in_array($estado, ['activo', 'ACTIVO'], true)) {
            return 'El servicio no está activo en recaudaciones.';
        }

        // Hay tarifas horarias espejadas pero algo más las frena (raro; se deja el genérico).
        if ($tarifasEspejo->isNotEmpty()) {
            return 'Este servicio no puede reservarse online en este momento.';
        }

        // Sin tarifas HORARIAS espejadas. ¿El servicio vivo trae alguna tarifa,
        // aunque sea de perfil (piscina/instituciones)? Entonces NO es "sin
        // tarifario": es un servicio gestionado por perfil, no por franja horaria.
        $tieneTarifasSireb = collect($servicio['tarifas'] ?? [])->isNotEmpty();

        if ($tieneTarifasSireb) {
            return 'Este servicio se gestiona por perfil (piscina, instituciones) y no por franja horaria. Consultá precio y cupo en ventanilla.';
        }

        return 'Este servicio no tiene tarifa liquidable vigente. Consultá precio en ventanilla.';
    }
}
