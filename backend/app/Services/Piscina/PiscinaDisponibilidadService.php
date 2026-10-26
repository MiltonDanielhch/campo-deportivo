<?php

namespace App\Services\Piscina;

use App\Models\CampoDeportivo;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de disponibilidad de piscina.
 *
 * Calcula turnos disponibles con cupo por perfil para una fecha dada.
 */
class PiscinaDisponibilidadService
{
    public function __construct(
        private PiscinaTarifaResolver $tarifaResolver,
    ) {
    }

    /**
     * Obtiene turnos disponibles para un campo piscina en una fecha.
     *
     * @param string $campoId
     * @param string $fecha Formato Y-m-d
     * @return array
     */
    public function obtenerTurnosDisponibles(string $campoId, string $fecha): array
    {
        $campo = CampoDeportivo::find($campoId);
        if (!$campo) {
            throw new \InvalidArgumentException('Campo no encontrado');
        }

        if ($campo->modalidad_reserva?->value !== 'turno_por_perfil') {
            return ['campo_id' => $campoId, 'fecha' => $fecha, 'abierto' => false, 'turnos' => []];
        }

        $fechaCarbon = Carbon::parse($fecha);
        $hoy = Carbon::now();
        $maxAnticipacion = 60;

        if ($fechaCarbon->lt($hoy->startOfDay())) {
            return ['campo_id' => $campoId, 'fecha' => $fecha, 'abierto' => false, 'turnos' => []];
        }
        if ($fechaCarbon->gt($hoy->copy()->addDays($maxAnticipacion)->endOfDay())) {
            return ['campo_id' => $campoId, 'fecha' => $fecha, 'abierto' => false, 'turnos' => []];
        }

        $turnos = PiscinaTurno::where('campo_id', $campoId)
            ->where('fecha', $fecha)
            ->where('estado', 'activo')
            ->with('perfiles')
            ->orderBy('hora_inicio')
            ->get();

        $turnosData = [];

        foreach ($turnos as $turno) {
            $perfilesData = [];
            $sinTarifa = [];   // [FIX B70] acumulo los perfiles que se caen por falta de tarifa

            foreach ($turno->perfiles as $perfilTurno) {
                if ($perfilTurno->estado !== 'activo') {
                    continue;
                }

                $tarifa = $this->tarifaResolver->resolver(
                    $campoId,
                    $perfilTurno->perfil,
                    $turno->hora_inicio,
                );

                if (!$tarifa) {
                    $sinTarifa[] = $perfilTurno->perfil;   // [FIX B70] ya no es silencio: lo registro
                    continue;
                }

                $horario = $this->determinarHorario($turno->hora_inicio, $campo->hora_inicio_noche);

                $perfilesData[] = [
                    'perfil' => $perfilTurno->perfil,
                    'etiqueta' => $this->etiquetaPerfil($perfilTurno->perfil),
                    'precio' => (float) $tarifa->precio_por_hora,
                    'precio_unitario' => (float) $perfilTurno->precio_unitario,
                    'cupo' => $perfilTurno->cupo,
                    'disponible' => max(0, (int) $perfilTurno->cupo - (int) $perfilTurno->reservado),
                    'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                    'horario' => $horario,
                ];
            }

            // [FIX B70] incluyo el turno si tiene perfiles ofrecidos O si tuvo caídas,
            // para que la señal de "faltan perfiles" llegue al front.
            if (!empty($perfilesData) || !empty($sinTarifa)) {
                $turnosData[] = [
                    'id' => $turno->id,
                    'hora_inicio' => substr($turno->hora_inicio, 0, 5),
                    'hora_fin' => substr($turno->hora_fin, 0, 5),
                    'perfiles' => $perfilesData,
                    'perfiles_sin_tarifa' => $sinTarifa,   // [FIX B70]
                ];
            }
        }

        return ['campo_id' => $campoId, 'fecha' => $fecha, 'abierto' => true, 'turnos' => $turnosData];
    }

    /**
     * Determina si un horario es diurno o nocturno.
     */
    private function determinarHorario(string $horaInicio, ?string $horaInicioNoche): string
    {
        if (!$horaInicioNoche) {
            return 'diurno';
        }

        $segundosInicio = $this->aSegundos($horaInicio);
        $segundosNoche = $this->aSegundos($horaInicioNoche);

        return $segundosInicio < $segundosNoche ? 'diurno' : 'nocturno';
    }

    /**
     * Convierte hora HH:MM:SS a segundos.
     */
    private function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));
        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }

    /**
     * Etiqueta legible para el perfil.
     */
    private function etiquetaPerfil(string $perfil): string
    {
        return match ($perfil) {
            'mayor_12' => 'Mayores de 12',
            'menor_12' => 'Menores de 12',
            'institucion' => 'Institución',
            default => $perfil,
        };
    }
}
