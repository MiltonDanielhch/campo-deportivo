<?php

namespace App\Services;

use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de versionado de tarifas (HU-A3).
 *
 * Garantiza que nunca haya dos tarifas activas para el mismo campo:
 *   - Nivel de aplicación: transacción con lockForUpdate() (lock pesimista)
 *   - Nivel de base de datos: índice único parcial uq_tarifa_activa
 *
 * Cada cambio de tarifa queda registrado en la bitácora de auditoría.
 */
class TarifaCampoService
{
    /**
     * Crea una nueva tarifa, cerrando automáticamente la anterior.
     * Transacción con lock pesimista para evitar condiciones de carrera.
     */
    public function actualizarTarifa(CampoDeportivo $campo, float $nuevoPrecio): TarifaCampo
    {
        $usuarioId = auth()->id();

        return DB::transaction(function () use ($campo, $nuevoPrecio, $usuarioId): TarifaCampo {
            // 1. Buscar la tarifa activa actual con bloqueo pesimista.
            //    El lockForUpdate() impide que otra transacción la lea/modifique
            //    hasta que esta transacción termine.
            $tarifaAnterior = TarifaCampo::where('campo_id', $campo->id)
                ->whereNull('vigente_hasta')
                ->lockForUpdate()
                ->first();

            // 2. Cerrar la tarifa anterior si existe.
            if ($tarifaAnterior) {
                $tarifaAnterior->update(['vigente_hasta' => now()]);
            }

            // 3. Crear la nueva tarifa activa.
            $nuevaTarifa = TarifaCampo::create([
                'campo_id' => $campo->id,
                'precio_por_hora' => $nuevoPrecio,
                'vigente_desde' => now(),
                'vigente_hasta' => null,
                'creado_por' => $usuarioId,
            ]);

            // 4. Registrar en auditoría.
            AuditoriaService::registrar(
                tabla: 'tarifas_campo',
                registroId: $nuevaTarifa->id,
                accion: 'crear_tarifa',
                usuarioId: $usuarioId,
                datosAnteriores: $tarifaAnterior?->only(['id', 'precio_por_hora', 'vigente_desde', 'vigente_hasta']),
                datosNuevos: $nuevaTarifa->only(['id', 'precio_por_hora', 'vigente_desde', 'vigente_hasta']),
            );

            return $nuevaTarifa;
        });
    }

    /**
     * Obtiene la tarifa activa de un campo (si existe).
     */
    public function tarifaActiva(CampoDeportivo $campo): ?TarifaCampo
    {
        return TarifaCampo::where('campo_id', $campo->id)
            ->whereNull('vigente_hasta')
            ->first();
    }

    /**
     * Historial completo de tarifas de un campo,
     * ordenado por vigencia descendente (la más reciente primero).
     */
    public function historial(CampoDeportivo $campo): Collection
    {
        return TarifaCampo::where('campo_id', $campo->id)
            ->with('creadoPor:id,nombre_completo')
            ->orderByDesc('vigente_desde')
            ->get();
    }
}
