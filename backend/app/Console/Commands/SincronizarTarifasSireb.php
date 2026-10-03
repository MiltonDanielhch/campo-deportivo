<?php

namespace App\Console\Commands;

use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Alinea tarifas_campo con el tarifario vigente de SIREB.
 *
 * Regla de oro del contrato: SIREB es la fuente de verdad de precios.
 * La tabla local queda como ESPEJO VALIDADO para que la estimación
 * que ve el ciudadano antes del submit coincida con la liquidación real.
 *
 * Por cada campo con servicio_sireb_id mapeado:
 *  - etiqueta SIREB "Diurno"   → tipo_tarifa 'diurna'
 *  - etiqueta SIREB "Nocturno" → tipo_tarifa 'nocturna'
 *  - si la tarifa local activa difiere → actualiza precio_por_hora
 *  - si no existe → la crea
 *  - discrepancias de estructura → se reportan, no se tocan
 */
class SincronizarTarifasSireb extends Command
{
    protected $signature = 'sireb:sincronizar-tarifas
        {--dry-run : Solo reporta discrepancias sin escribir nada}
        {--force : Forzar sincronización incluso si hay errores}';

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
                        TarifaCampo::create([
                            'campo_id' => $campo->id,
                            'tipo_tarifa' => $tipo,
                            'precio_por_hora' => $precioSireb,
                            'vigente_desde' => now(),
                            'vigente_hasta' => null,
                            'creado_por' => null,
                        ]);
                    }
                    $filas[] = [$campo->codigo, $tipo, '—', $precioSireb, $dryRun ? 'FALTANTE (dry-run)' : 'Creada desde SIREB'];
                    continue;
                }

                if (abs((float) $local->precio_por_hora - $precioSireb) > 0.005) {
                    if (! $dryRun) {
                        $local->update(['precio_por_hora' => $precioSireb]);
                    }
                    $filas[] = [$campo->codigo, $tipo, (float) $local->precio_por_hora, $precioSireb, $dryRun ? 'DIFIERE (dry-run)' : 'Actualizada desde SIREB'];
                } else {
                    $filas[] = [$campo->codigo, $tipo, (float) $local->precio_por_hora, $precioSireb, 'OK'];
                }
            }
        }

        $this->table(['Campo', 'Tipo', 'Precio local', 'Precio SIREB', 'Acción'], $filas);

        Log::channel('sireb')->info('sireb:sincronizar-tarifas ejecutado', [
            'dry_run' => $dryRun,
            'campos_procesados' => $campos->count(),
        ]);

        return self::SUCCESS;
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
