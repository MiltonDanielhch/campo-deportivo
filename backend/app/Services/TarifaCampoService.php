<?php

namespace App\Services;

use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consulta de tarifas de un campo (HU-A3).
 *
 * Las tarifas son de SOLO LECTURA en este sistema: el precio lo define SIREB
 * (fuente de verdad del contrato de recaudaciones) y `sireb:sincronizar-tarifas`
 * las mantiene espejadas en tarifas_campo. Acá ya no hay forma de crear ni
 * modificar una tarifa.
 *
 * Cada campo puede tener hasta 2 tarifas activas (una por tipo):
 *   - diurna: bloques que inician antes de hora_inicio_noche
 *   - nocturna: bloques que inician a esa hora o después
 */
class TarifaCampoService
{
    /**
     * Devuelve las tarifas activas de un campo agrupadas por tipo.
     *
     * @return array{diurna: ?TarifaCampo, nocturna: ?TarifaCampo}
     */
    public function tarifasActivas(CampoDeportivo $campo): array
    {
        $tarifas = TarifaCampo::where('campo_id', $campo->id)
            ->whereNull('vigente_hasta')
            ->get()
            ->keyBy('tipo_tarifa');

        return [
            'diurna' => $tarifas->get('diurna'),
            'nocturna' => $tarifas->get('nocturna'),
        ];
    }

    /**
     * Historial completo, ordenado por vigencia descendente.
     */
    public function historial(CampoDeportivo $campo): Collection
    {
        return TarifaCampo::where('campo_id', $campo->id)
            ->with('creadoPor:id,nombre_completo')
            ->orderByDesc('vigente_desde')
            ->get();
    }
}
