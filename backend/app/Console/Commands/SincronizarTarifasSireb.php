<?php

namespace App\Console\Commands;

use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use App\Services\AuditoriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Alinea tarifas_campo con el tarifario vigente de SIREB.
 *
 * Regla de oro del contrato: SIREB es la fuente de verdad de precios.
 * La tabla local queda como ESPEJO VALIDADO para que la estimación
 * que ve el ciudadano antes del submit coincida con la liquidación real.
 *
 * Ninguna persona fija precios en este sistema: el endpoint manual se
 * eliminó y el formulario del panel pasó a solo lectura. Lo único que
 * escribe tarifas_campo es este comando, y siempre con creado_por = null.
 *
 * Por cada campo con servicio_sireb_id mapeado:
 *  - etiqueta SIREB "Diurno"   → tipo_tarifa 'diurna'
 *  - etiqueta SIREB "Nocturno" → tipo_tarifa 'nocturna'
 *  - si la tarifa local activa difiere → cierra la versión vigente y crea
 *    una nueva, así el historial conserva el precio anterior
 *  - si no existe → la crea
 *  - discrepancias de estructura → se reportan, no se tocan
 */
class SincronizarTarifasSireb extends Command
{
    protected $signature = 'sireb:sincronizar-tarifas
        {--dry-run : Solo reporta discrepancias sin escribir nada}';

    protected $description = 'Alinea tarifas_campo con el tarifario vigente de SIREB (espejo validado)';

    public function handle(RecaudacionesApiClientInterface $client): int
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

        $campos = CampoDeportivo::whereNotNull('servicio_sireb_id')
            ->where('estado', 'activo')
            ->get();

        foreach ($campos as $campo) {
            $servicio = $serviciosPorId->get($campo->servicio_sireb_id);

            if (! $servicio) {
                $filas[] = [$campo->codigo, '—', '—', '—', 'Servicio no encontrado en catálogo'];
                continue;
            }

            foreach ($servicio['tarifas'] ?? [] as $tarifaSireb) {
                $tipo = $this->tipoDesdeEtiqueta($tarifaSireb['etiqueta'] ?? '');

                if ($tipo === null) {
                    $filas[] = [$campo->codigo, $tarifaSireb['etiqueta'], '—', $tarifaSireb['monto'], 'Etiqueta no reconocida (se ignora)'];
                    continue;
                }

                $precioSireb = (float) $tarifaSireb['monto'];

                $local = TarifaCampo::where('campo_id', $campo->id)
                    ->where('tipo_tarifa', $tipo)
                    ->whereNull('vigente_hasta')
                    ->first();

                if ($local === null) {
                    if (! $dryRun) {
                        $this->crearVersion($campo, $tipo, $precioSireb, null);
                    }
                    $filas[] = [$campo->codigo, $tipo, '—', $precioSireb, $dryRun ? 'FALTANTE (dry-run)' : 'Creada desde SIREB'];
                    continue;
                }

                $precioLocal = (float) $local->precio_por_hora;

                if (abs($precioLocal - $precioSireb) <= 0.005) {
                    $filas[] = [$campo->codigo, $tipo, $precioLocal, $precioSireb, 'OK'];
                    continue;
                }

                if (! $dryRun) {
                    $this->crearVersion($campo, $tipo, $precioSireb, $local);
                }

                $filas[] = [
                    $campo->codigo,
                    $tipo,
                    $precioLocal,
                    $precioSireb,
                    $dryRun ? 'DIFIERE (dry-run)' : 'Nueva versión desde SIREB',
                ];
            }
        }

        $this->table(['Campo', 'Tipo', 'Precio local', 'Precio SIREB', 'Acción'], $filas);

        Log::channel('sireb')->info('sireb:sincronizar-tarifas ejecutado', [
            'dry_run' => $dryRun,
            'campos_procesados' => $campos->count(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Cierra la versión vigente (si la hay) y crea la nueva.
     *
     * No se actualiza el precio en sitio: así el historial sigue mostrando
     * cuánto costaba antes y desde cuándo rige el precio nuevo. creado_por
     * va en null porque el precio lo define SIREB, no un funcionario.
     */
    private function crearVersion(
        CampoDeportivo $campo,
        string $tipo,
        float $precio,
        ?TarifaCampo $anterior,
    ): TarifaCampo {
        return DB::transaction(function () use ($campo, $tipo, $precio, $anterior): TarifaCampo {
            $snapshotAnterior = $anterior?->only([
                'id', 'tipo_tarifa', 'precio_por_hora', 'vigente_desde', 'vigente_hasta',
            ]);

            if ($anterior) {
                $anterior->update(['vigente_hasta' => now()]);
            }

            $nueva = TarifaCampo::create([
                'campo_id' => $campo->id,
                'tipo_tarifa' => $tipo,
                'precio_por_hora' => $precio,
                'vigente_desde' => now(),
                'vigente_hasta' => null,
                'creado_por' => null,
            ]);

            AuditoriaService::registrar(
                tabla: 'tarifas_campo',
                registroId: $nueva->id,
                accion: 'sincronizar_tarifa_sireb',
                usuarioId: null,
                datosAnteriores: $snapshotAnterior,
                datosNuevos: $nueva->only([
                    'id', 'tipo_tarifa', 'precio_por_hora', 'vigente_desde', 'vigente_hasta',
                ]),
            );

            return $nueva;
        });
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
