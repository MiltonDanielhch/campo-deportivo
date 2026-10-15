<?php

namespace App\Services\Piscina;

use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve tarifa de piscina desde el espejo SIREB.
 *
 * Dado: campo_id, perfil, hora_inicio
 * Devuelve: TarifaCampo válida para ese perfil/horario
 *
 * Reglas:
 * - perfil debe coincidir
 * - liquidable = true
 * - tarifario_estado = vigente
 * - vigente_hasta = null (tarifa activa)
 * - clasificacion: unica (cualquier turno) o diurna/nocturna (según hora)
 */
class PiscinaTarifaResolver
{
    public function resolver(string $campoId, string $perfil, string $horaInicio): ?TarifaCampo
    {
        $campo = CampoDeportivo::find($campoId);
        if (!$campo) {
            Log::warning('Campo no encontrado al resolver tarifa piscina', [
                'campo_id' => $campoId,
            ]);
            return null;
        }

        // Determinar clasificación según hora
        $clasificacion = $this->determinarClasificacion($horaInicio, $campo->hora_inicio_noche);

        // Buscar tarifa vigente para el perfil
        $tarifa = TarifaCampo::query()
            ->where('campo_id', $campoId)
            ->where('perfil', $perfil)
            ->where('liquidable', true)
            ->where('tarifario_estado', 'vigente')
            ->whereNull('vigente_hasta')
            ->where(function ($query) use ($clasificacion) {
                // Si es unica, acepta cualquier horario
                // Si es diurna/nocturna, debe coincidir con la clasificación calculada
                $query->where('clasificacion', 'unica')
                    ->orWhere('clasificacion', $clasificacion);
            })
            ->first();

        if (!$tarifa) {
            Log::warning('No se encontró tarifa vigente para piscina', [
                'campo_id' => $campoId,
                'perfil' => $perfil,
                'hora_inicio' => $horaInicio,
                'clasificacion' => $clasificacion,
            ]);
        }

        return $tarifa;
    }

    /**
     * Determina si un horario es diurno o nocturno según la hora_inicio_noche del campo.
     */
    private function determinarClasificacion(string $horaInicio, ?string $horaInicioNoche): string
    {
        if (!$horaInicioNoche) {
            // Default: diurno si no está configurado
            return 'diurna';
        }

        $segundosInicio = $this->aSegundos($horaInicio);
        $segundosNoche = $this->aSegundos($horaInicioNoche);

        return $segundosInicio < $segundosNoche ? 'diurna' : 'nocturna';
    }

    /**
     * Convierte hora HH:MM:SS a segundos desde medianoche.
     */
    private function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));
        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }
}
