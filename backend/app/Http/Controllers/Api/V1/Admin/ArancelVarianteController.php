<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Illuminate\Http\JsonResponse;

/**
 * Variantes arancelarias de un campo físico.
 *
 * P8.2C: endpoint solo lectura para el modal de ventanilla.
 * No crea reservas, no toca SIREB, no expone datos sensibles.
 */
class ArancelVarianteController extends Controller
{
    public function index(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $variantes = ArancelVariante::query()
            ->where('campo_id', $campoDeportivo->id)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $servicioIds = $variantes
            ->pluck('servicio_sireb_id')
            ->filter()
            ->unique()
            ->values();

        $tarifasPorServicio = collect();

        if ($servicioIds->isNotEmpty()) {
            $tarifasPorServicio = TarifaCampo::paraGrillaHoraria()
                ->where('campo_id', $campoDeportivo->id)
                ->whereIn('servicio_sireb_id', $servicioIds)
                ->get()
                ->groupBy('servicio_sireb_id');
        }

        return response()->json([
            'data' => $variantes->map(function (ArancelVariante $variante) use ($tarifasPorServicio) {
                $tarifas = $tarifasPorServicio->get($variante->servicio_sireb_id, collect());

                return [
                    'id' => $variante->id,
                    'codigo' => $variante->codigo,
                    'nombre' => $variante->nombre,
                    'descripcion' => $variante->descripcion,
                    'tipo_uso' => $variante->tipo_uso,
                    'servicio_sireb_id' => $variante->servicio_sireb_id,
                    'servicio_sireb_codigo' => $variante->servicio_sireb_codigo,
                    'unidad_medida' => $variante->unidad_medida,
                    'visible_publico' => $variante->visible_publico,
                    'activo' => $variante->activo,
                    'orden' => $variante->orden,
                    'tarifas' => $tarifas->map(fn (TarifaCampo $tarifa) => [
                        'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                        'etiqueta' => $tarifa->etiqueta_sireb,
                        'clasificacion' => $tarifa->clasificacion,
                        'monto' => (float) $tarifa->precio_por_hora,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }


    /**
     * Servicios adicionales de un campo físico.
     *
     * P8.3A: limpieza, energía, etc.
     * No son franjas horarias y no ocupan disponibilidad física.
     */
    public function serviciosAdicionales(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $variantes = ArancelVariante::query()
            ->where('campo_id', $campoDeportivo->id)
            ->where('activo', true)
            ->where(function ($query) {
                $query->where('unidad_medida', 'servicio')
                    ->orWhere('tipo_uso', 'servicio_adicional');
            })
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $servicioIds = $variantes
            ->pluck('servicio_sireb_id')
            ->filter()
            ->unique()
            ->values();

        $tarifasPorServicio = collect();

        if ($servicioIds->isNotEmpty()) {
            $tarifasPorServicio = TarifaCampo::query()
                ->where('campo_id', $campoDeportivo->id)
                ->whereNull('vigente_hasta')
                ->where('liquidable', true)
                ->where('tarifario_estado', 'vigente')
                ->whereIn('servicio_sireb_id', $servicioIds)
                ->get()
                ->groupBy('servicio_sireb_id');
        }

        return response()->json([
            'data' => $variantes->map(function (ArancelVariante $variante) use ($tarifasPorServicio) {
                $tarifas = $tarifasPorServicio->get($variante->servicio_sireb_id, collect());

                return [
                    'id' => $variante->id,
                    'codigo' => $variante->codigo,
                    'nombre' => $variante->nombre,
                    'descripcion' => $variante->descripcion,
                    'tipo_uso' => $variante->tipo_uso,
                    'servicio_sireb_id' => $variante->servicio_sireb_id,
                    'servicio_sireb_codigo' => $variante->servicio_sireb_codigo,
                    'unidad_medida' => $variante->unidad_medida,
                    'visible_publico' => $variante->visible_publico,
                    'activo' => $variante->activo,
                    'orden' => $variante->orden,
                    'tarifas' => $tarifas->map(fn (TarifaCampo $tarifa) => [
                        'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                        'etiqueta' => $tarifa->etiqueta_sireb,
                        'clasificacion' => $tarifa->clasificacion,
                        'monto' => (float) $tarifa->precio_por_hora,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
