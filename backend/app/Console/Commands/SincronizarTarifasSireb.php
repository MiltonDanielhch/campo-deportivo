<?php

namespace App\Console\Commands;

use App\Enums\TipoTarifaSireb;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use App\Services\AuditoriaService;
use App\Services\Sireb\ClasificacionTarifa;
use App\Services\Sireb\ClasificadorTarifaSireb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarTarifasSireb extends Command
{
    protected $signature = 'sireb:sincronizar-tarifas {--dry-run : Solo reporta sin escribir}';
    protected $description = 'Alinea tarifas_campo con el tarifario vigente de SIREB (espejo validado)';

    public function handle(RecaudacionesApiClientInterface $client, ClasificadorTarifaSireb $clasificador): int
    {
        try {
            $catalogo = $client->listarCatalogo(pagina: 1, porPagina: 100);
        } catch (\Throwable $e) {
            $this->error('No se pudo obtener el catálogo de SIREB: '.$e->getMessage());
            return self::FAILURE;
        }

        $serviciosPorId = collect($catalogo)->keyBy('id');
        $dryRun = (bool) $this->option('dry-run');
        $filas = [];
        $campos = CampoDeportivo::whereNotNull('servicio_sireb_id')->where('estado', 'activo')->get();

        foreach ($campos as $campo) {
            $servicio = $serviciosPorId->get($campo->servicio_sireb_id);
            if (! $servicio) {
                $filas[] = [$campo->codigo, '—', '—', '—', '—', 'Servicio no encontrado'];
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
        Log::channel('sireb')->info('sireb:sincronizar-tarifas', ['dry_run' => $dryRun, 'campos' => $campos->count(), 'no_clasificadas' => $noClas]);
        return self::SUCCESS;
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
}
