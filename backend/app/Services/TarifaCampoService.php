<?php

namespace App\Services;

use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Consulta de tarifas de un campo (HU-A3).
 *
 * Las tarifas son de SOLO LECTURA en este sistema: el precio lo define SIREB
 * (fuente de verdad del contrato de recaudaciones) y `sireb:sincronizar-tarifas`
 * las mantiene espejadas en tarifas_campo.
 *
 * El panel admin debe mostrar la verdad del espejo:
 *  - tarifas horarias ofrecibles: diurna / nocturna / unica, sin perfil.
 *  - tarifas espejadas no horarias: con perfil (piscina/institución) o no clasificadas.
 *  - historial completo.
 *  - última sincronización desde SIREB.
 */
class TarifaCampoService
{
    /**
     * Payload completo para el panel de tarifas.
     *
     * @return array{
     *     hora_inicio_noche: string|null,
     *     sincronizado_en: string|null,
     *     activas: array{diurna: ?TarifaCampo, nocturna: ?TarifaCampo, unica: ?TarifaCampo},
     *     no_horarias: Collection<int, TarifaCampo>,
     *     historial: Collection<int, TarifaCampo>
     * }
     */
    public function panel(CampoDeportivo $campo): array
    {
        // Tarifas horarias ofrecibles en grilla de canchas.
        // Excluye piscina/perfiles y no_clasificadas por definición del scope.
        $horarias = TarifaCampo::paraGrillaHoraria()
            ->where('campo_id', $campo->id)
            ->with('creadoPor:id,nombre_completo')
            ->get()
            ->keyBy('clasificacion');

        // Tarifas espejadas desde SIREB que NO deben entrar a grilla horaria:
        // - con perfil (piscina, instituciones, etc.)
        // - o clasificadas como no_clasificada.
        $noHorarias = TarifaCampo::query()
            ->where('campo_id', $campo->id)
            ->whereNull('vigente_hasta')
            ->where('liquidable', true)
            ->where('tarifario_estado', 'vigente')
            ->where(function ($query) {
                $query->whereNotNull('perfil')
                    ->orWhere('clasificacion', 'no_clasificada');
            })
            ->with('creadoPor:id,nombre_completo')
            ->orderBy('perfil')
            ->orderBy('clasificacion')
            ->get();

        $ultimoSincronizado = TarifaCampo::where('campo_id', $campo->id)
            ->max('sincronizado_en');

        $historial = TarifaCampo::where('campo_id', $campo->id)
            ->with('creadoPor:id,nombre_completo')
            ->orderByDesc('vigente_desde')
            ->get();

        return [
            'hora_inicio_noche' => $campo->hora_inicio_noche,
            'sincronizado_en' => $ultimoSincronizado
                ? Carbon::parse($ultimoSincronizado)->toIso8601String()
                : null,
            'activas' => [
                'diurna' => $horarias->get('diurna'),
                'nocturna' => $horarias->get('nocturna'),
                'unica' => $horarias->get('unica'),
            ],
            'no_horarias' => $noHorarias,
            'historial' => $historial,
        ];
    }

    /**
     * Compatibilidad con llamadas antiguas.
     *
     * @return array{diurna: ?TarifaCampo, nocturna: ?TarifaCampo, unica: ?TarifaCampo}
     */
    public function tarifasActivas(CampoDeportivo $campo): array
    {
        $tarifas = TarifaCampo::paraGrillaHoraria()
            ->where('campo_id', $campo->id)
            ->get()
            ->keyBy('clasificacion');

        return [
            'diurna' => $tarifas->get('diurna'),
            'nocturna' => $tarifas->get('nocturna'),
            'unica' => $tarifas->get('unica'),
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
