<?php

namespace App\Console\Commands;

use App\Enums\TipoTarifaSireb;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use App\Services\AuditoriaService;
use App\Services\CatalogoSirebService;
use App\Services\Sireb\ClasificacionTarifa;
use App\Services\Sireb\ClasificadorTarifaSireb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ArancelVariante;
use Illuminate\Support\Collection;

class SincronizarTarifasSireb extends Command
{
    protected $signature = 'sireb:sincronizar-tarifas {--dry-run : Solo reporta sin escribir}';
    protected $description = 'Alinea tarifas_campo con el tarifario vigente de SIREB (espejo validado)';

    public function handle(CatalogoSirebService $catalogoSireb, ClasificadorTarifaSireb $clasificador): int
    {
        try {
            // Usa el service (que pagina) en lugar de listarCatalogo() directo,
            // así no se pierden servicios que vivan en páginas > 1.
            $catalogo = $catalogoSireb->refrescarCatalogo();
        } catch (\Throwable $e) {
            $this->error('No se pudo obtener el catálogo de SIREB: '.$e->getMessage());
            return self::FAILURE;
        }

        $serviciosPorId = collect($catalogo)->keyBy('id');
        $serviciosPorCodigo = collect($catalogo)->keyBy(fn ($s) => (string) ($s['codigo'] ?? ''));
        $dryRun = (bool) $this->option('dry-run');

        $this->sincronizarVariantes($serviciosPorCodigo, $clasificador, $dryRun);

        // ─── Pre-paso: auto-crear campo_deportivo para servicios SIREB nuevos ───
        // Cualquier servicio SIREB con tarifario=liquidable + estado=activo
        // que aún no tenga campo local, se materializa como campo_deportivo
        // en estado "pendiente" (modalidad sin_reserva_online) para que:
        //   1) El operador lo vea aparecer en "Campos locales" sin acción manual.
        //   2) "Sincronizar tarifas" ya pueda espejar sus precios en este mismo
        //      pase (no quedan en limbo hasta que alguien pulse "Crear campo").
        // El operador completa luego nombre, dirección, coordenadas, tipo_campo.
        $autoCreados = [];
        if (! $dryRun) {
            $campos = $this->asegurarCamposLocales($serviciosPorId, $autoCreados);
        } else {
            $campos = CampoDeportivo::whereNotNull('servicio_sireb_id')->where('estado', 'activo')->get();
        }

        $filas = [];
        foreach ($campos as $campo) {
            $servicio = $serviciosPorId->get($campo->servicio_sireb_id);
            if (! $servicio) {
                $filas[] = [$campo->codigo, '—', '—', '—', '—', 'Servicio no encontrado'];
                continue;
            }
            // Saltar servicios SIREB inactivos: no se espeja su tarifa.
            if (($servicio['estado'] ?? null) !== 'activo') {
                continue;
            }
            foreach ($servicio['tarifas'] ?? [] as $tarifaSireb) {
                $clas = $clasificador->clasificar($tarifaSireb, $servicio);
                $precioSireb = (float) ($tarifaSireb['monto'] ?? 0);
                $tarifaSirebId = $tarifaSireb['id'] ?? null;
                if ($tarifaSirebId === null) {
                    $filas[] = [$campo->codigo, $tarifaSireb['etiqueta'] ?? '—', '—', $precioSireb, $clas->tipo->value, 'Sin tarifa_sireb_id'];
                    continue;
                }
                $local = TarifaCampo::where('campo_id', $campo->id)->where('tarifa_sireb_id', $tarifaSirebId)->whereNull('vigente_hasta')->first();
                $difiere = $local && (
                    abs((float) $local->precio_por_hora - $precioSireb) > 0.005
                    || $local->clasificacion !== $clas->tipo->value
                    || ($local->perfil ?? null) !== $clas->perfil
                );
                $accion = match (true) {
                    $local === null => $dryRun ? 'FALTANTE (dry-run)' : 'Creada desde SIREB',
                    $difiere => $dryRun ? 'CAMBIA (dry-run)' : 'Nueva versión desde SIREB',
                    default => 'OK',
                };
                if (! $dryRun && ($local === null || $difiere)) {
                    $this->espejar($campo, $servicio, $tarifaSireb, $clas, $precioSireb, $local);
                }
                $filas[] = [$campo->codigo, $tarifaSireb['etiqueta'] ?? '—', $local ? (float) $local->precio_por_hora : '—', $precioSireb, $clas->tipo->value.($clas->perfil ? " ({$clas->perfil})" : ''), $accion];
            }
        }

        $this->table(['Campo', 'Etiqueta SIREB', 'Precio local', 'Precio SIREB', 'Clasificación', 'Acción'], $filas);
        $noClas = collect($filas)->filter(fn ($f) => str_starts_with((string) $f[4], 'no_clasificada'))->count();
        if ($noClas > 0) {
            $this->warn("{$noClas} tarifa(s) no_clasificada(s): no entran a grilla horaria.");
        }
        if (! empty($autoCreados)) {
            $this->info('Campos auto-creados (pendientes de completar por el operador):');
            $this->table(['Código', 'Nombre SIREB'], array_map(fn ($c) => [$c['codigo'], $c['nombre']], $autoCreados));
        }
        Log::channel('sireb')->info('sireb:sincronizar-tarifas', [
            'dry_run' => $dryRun,
            'campos' => $campos->count(),
            'no_clasificadas' => $noClas,
            'auto_creados' => count($autoCreados),
        ]);
        return self::SUCCESS;
    }

    /**
     * Para cada servicio SIREB activo con tarifario=liquidable que aún no
     * tiene campo_deportivo local, crea uno en estado "pendiente" para que
     * el siguiente paso (espejado de tarifas) ya tenga dónde escribir.
     *
     * El campo se crea con modalidad_reserva='sin_reserva_online' para que
     * NUNCA aparezca como reservable al público hasta que el operador
     * complete dirección/coordenadas/tipo desde la UI de Campos locales.
     */
    private function asegurarCamposLocales(\Illuminate\Support\Collection $serviciosPorId, array &$autoCreados): \Illuminate\Database\Eloquent\Collection
    {
        $existentesIds = CampoDeportivo::whereNotNull('servicio_sireb_id')
            ->pluck('servicio_sireb_id')
            ->all();
        $existentesSet = array_flip($existentesIds);

        // tipo_campo por defecto: el primero que exista. Como el operador
        // tendrá que revisarlo, el valor inicial es solo para satisfacer
        // la FK (la columna es NOT NULL y restrictOnDelete).
        $tipoCampoDefault = TipoCampo::orderBy('nombre')->value('id');

        $nuevos = [];
        foreach ($serviciosPorId as $servicio) {
            $id = $servicio['id'] ?? null;
            $codigo = $servicio['codigo'] ?? null;
            $nombre = $servicio['nombre'] ?? null;
            if (! $id || ! $codigo || ! $nombre) {
                continue;
            }
            if (isset($existentesSet[$id])) {
                continue;
            }
            if (ArancelVariante::where('servicio_sireb_codigo', $codigo)->exists()) {
                continue;
            }

            // Solo auto-crear para servicios que SIREB marca como cobrables.
            if (($servicio['estado'] ?? null) !== 'activo') {
                continue;
            }
            if (($servicio['tarifario'] ?? null) !== 'liquidable') {
                continue;
            }

            $campo = CampoDeportivo::create([
                'tipo_campo_id' => $tipoCampoDefault,
                'codigo' => 'SIR-'.substr($codigo, 0, 25), // VARCHAR(30), único
                'nombre' => substr($nombre, 0, 150),
                'direccion' => 'Pendiente de completar por el operador',
                'latitud' => 0,
                'longitud' => 0,
                'estado' => 'activo',
                'servicio_sireb_id' => $id,
                'servicio_sireb_codigo' => substr($codigo, 0, 30),
                'modalidad_reserva' => 'sin_reserva_online',
            ]);

            AuditoriaService::registrar(
                tabla: 'campos_deportivos',
                registroId: $campo->id,
                accion: 'auto_crear_desde_sireb',
                usuarioId: null,
                datosAnteriores: null,
                datosNuevos: $campo->only(['id', 'codigo', 'nombre', 'servicio_sireb_id', 'servicio_sireb_codigo', 'modalidad_reserva'])
            );

            $autoCreados[] = ['codigo' => $campo->codigo, 'nombre' => $campo->nombre];
            $nuevos[] = $campo;
        }

        // Devolver la colección COMPLETA (auto-creados + existentes) para que
        // el bucle de espejado de tarifas procese los nuevos sin recargar.
        $existentes = CampoDeportivo::whereNotNull('servicio_sireb_id')
            ->where('estado', 'activo')
            ->get();
        return $existentes;
    }

    private function espejar(CampoDeportivo $campo, array $servicio, array $tarifaSireb, ClasificacionTarifa $clas, float $precio, ?TarifaCampo $anterior): TarifaCampo
    {
        return DB::transaction(function () use ($campo, $servicio, $tarifaSireb, $clas, $precio, $anterior): TarifaCampo {
            $snapshot = $anterior?->only(['id', 'tipo_tarifa', 'clasificacion', 'perfil', 'precio_por_hora', 'vigente_desde', 'vigente_hasta', 'tarifario_estado', 'liquidable']);
            if ($anterior) {
                $anterior->update(['vigente_hasta' => now()]);
            }
            $tipoViejo = match ($clas->tipo) {
                TipoTarifaSireb::Diurna => 'diurna',
                TipoTarifaSireb::Nocturna => 'nocturna',
                default => null,
            };
            $ahora = now();
            $nueva = TarifaCampo::create([
                'campo_id' => $campo->id,
                'tarifa_sireb_id' => $tarifaSireb['id'],
                'servicio_sireb_id' => $servicio['id'] ?? $campo->servicio_sireb_id,
                'etiqueta_sireb' => $tarifaSireb['etiqueta'] ?? null,
                'liquidable' => (bool) ($tarifaSireb['liquidable'] ?? true),
                'tarifario_estado' => $tarifaSireb['tarifario_estado'] ?? null,
                'estado_tarifa_sireb' => $tarifaSireb['estado'] ?? null,
                'rango_desde' => $tarifaSireb['rango_desde'] ?? null,
                'rango_hasta' => $tarifaSireb['rango_hasta'] ?? null,
                'categorias_sireb' => $tarifaSireb['categorias'] ?? [],
                'perfil' => $clas->perfil,
                'clasificacion' => $clas->tipo->value,
                'tipo_tarifa' => $tipoViejo,
                'precio_por_hora' => $precio,
                'vigente_desde' => $ahora,
                'vigente_hasta' => null,
                'sincronizado_en' => $ahora,
                'creado_por' => null,
            ]);
            AuditoriaService::registrar(tabla: 'tarifas_campo', registroId: $nueva->id, accion: 'sincronizar_tarifa_sireb', usuarioId: null, datosAnteriores: $snapshot, datosNuevos: $nueva->only(['id', 'tarifa_sireb_id', 'clasificacion', 'perfil', 'precio_por_hora', 'vigente_desde', 'vigente_hasta', 'tarifario_estado', 'liquidable', 'sincronizado_en']));
            return $nueva;
        });
    }

    /**
     * Sincroniza arancel_variantes con el catálogo SIREB y espeja sus tarifas.
     *
     * Esto permite que un mismo campo físico tenga varias variantes arancelarias
     * sin crear un campo_deportivo distinto por cada servicio SIREB.
     */
    private function sincronizarVariantes(
        Collection $serviciosPorCodigo,
        ClasificadorTarifaSireb $clasificador,
        bool $dryRun,
    ): void {
        $variantes = ArancelVariante::with('campo')
            ->whereNotNull('servicio_sireb_codigo')
            ->get();

        if ($variantes->isEmpty()) {
            return;
        }

        $serviciosVariantesIds = [];

        foreach ($variantes as $variante) {
            if (! $variante->campo) {
                continue;
            }

            $servicio = $serviciosPorCodigo->get((string) $variante->servicio_sireb_codigo);

            if (! $servicio) {
                continue;
            }

            $servicioId = (string) ($servicio['id'] ?? '');

            if ($servicioId === '') {
                continue;
            }

            if ($variante->servicio_sireb_id !== $servicioId) {
                if (! $dryRun) {
                    $variante->update([
                        'servicio_sireb_id' => $servicioId,
                    ]);
                }
            }

            if (($servicio['estado'] ?? null) !== 'activo') {
                continue;
            }

            $serviciosVariantesIds[$servicioId] = true;

            if ($dryRun) {
                continue;
            }

            foreach ($servicio['tarifas'] ?? [] as $tarifaSireb) {
                $tarifaSirebId = $tarifaSireb['id'] ?? null;

                if ($tarifaSirebId === null) {
                    continue;
                }

                $clas = $clasificador->clasificar($tarifaSireb, $servicio);
                $precioSireb = (float) ($tarifaSireb['monto'] ?? 0);

                $local = TarifaCampo::query()
                    ->where('campo_id', $variante->campo_id)
                    ->where('servicio_sireb_id', $servicioId)
                    ->where('tarifa_sireb_id', $tarifaSirebId)
                    ->whereNull('vigente_hasta')
                    ->first();

                $difiere = $local && (
                    abs((float) $local->precio_por_hora - $precioSireb) > 0.005
                    || $local->clasificacion !== $clas->tipo->value
                    || ($local->perfil ?? null) !== $clas->perfil
                );

                if ($local === null || $difiere) {
                    $this->espejar(
                        $variante->campo,
                        $servicio,
                        $tarifaSireb,
                        $clas,
                        $precioSireb,
                        $local
                    );
                }
            }
        }

        if (! $dryRun) {
            $this->desactivarCamposDuplicadosDeVariantes(array_keys($serviciosVariantesIds));
        }
    }

    /**
     * Si SIREB antes auto-creó campos SIR-* para servicios que ahora son
     * variantes de un campo físico, esos campos quedan inactivos y sin vínculo
     * para evitar doble reserva del mismo espacio.
     */
    private function desactivarCamposDuplicadosDeVariantes(array $servicioIds): void
    {
        if (empty($servicioIds)) {
            return;
        }

        CampoDeportivo::query()
            ->where('codigo', 'like', 'SIR-%')
            ->whereIn('servicio_sireb_id', $servicioIds)
            ->update([
                'estado' => 'inactivo',
                'servicio_sireb_id' => null,
                'servicio_sireb_codigo' => null,
            ]);
    }
}
