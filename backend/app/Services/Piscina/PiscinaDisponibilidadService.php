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
    // [FIX piscina-tz] Mantener en sync con DisponibilidadService::TIMEZONE_BOLIVIA y
    // SolicitudReservaService::TIMEZONE_BOLIVIA. TODO: extraer a App\Support\FechaBolivia
    // para no duplicar el tz en tres services (misma deuda anotada en cancha).
    private const TIMEZONE_BOLIVIA = 'America/La_Paz';

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

        // [FIX piscina-tz] parsear la fecha pedida y el "ahora" en America/La_Paz, igual
        // que cancha. Antes Carbon::now() sin tz → en Docker UTC las 23:30 Bolivia ya era
        // mañana y el gate de "fecha pasada" rechazaba una fecha legítima de hoy.
        $fechaCarbon = Carbon::parse($fecha, self::TIMEZONE_BOLIVIA);
        $ahoraBolivia = self::ahoraBolivia();
        $hoyBolivia = $ahoraBolivia->copy()->startOfDay();
        $maxAnticipacion = 60;

        if ($fechaCarbon->lt($hoyBolivia)) {
            return ['campo_id' => $campoId, 'fecha' => $fecha, 'abierto' => false, 'turnos' => []];
        }
        if ($fechaCarbon->gt($hoyBolivia->copy()->addDays($maxAnticipacion)->endOfDay())) {
            return ['campo_id' => $campoId, 'fecha' => $fecha, 'abierto' => false, 'turnos' => []];
        }

        // [FIX piscina-pasada] espejo del gate 'pasada' de cancha: si la fecha pedida es
        // HOY en Bolivia, los turnos cuya hora de inicio ya corrió NO se ofrecen.
        // Acá EXCLUYO en vez de marcar 'pasada' porque el front de piscina no tiene la
        // rama que pinta el bloque apagado (a diferencia del de cancha, que la agregó en
        // B74). Marcar sin tocar el front dejaría la pasada seleccionable igual; excluir
        // cierra el hueco con menor superficie. Si el PO quiere pintar 'pasada' apagada
        // en piscina (como cancha), se agrega el campo al backend + la rama al front en
        // un frente aparte — no acá.
        $esHoyBolivia = $fechaCarbon->toDateString() === $ahoraBolivia->toDateString();
        $ahoraSeg = $esHoyBolivia ? $this->aSegundos($ahoraBolivia->format('H:i:s')) : -1;

        $turnos = PiscinaTurno::where('campo_id', $campoId)
            ->where('fecha', $fecha)
            ->where('estado', 'activo')
            ->with('perfiles')
            ->orderBy('hora_inicio')
            ->get();

        $turnosData = [];

        foreach ($turnos as $turno) {
            // [FIX piscina-pasada] turno de HOY ya corrido → no se ofrece.
            if ($esHoyBolivia && $this->aSegundos($turno->hora_inicio) <= $ahoraSeg) {
                continue;
            }

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
     * [FIX piscina-tz] Reloj oficial de Bolivia, independiente de APP_TIMEZONE.
     * Espejo de SolicitudReservaService::ahoraBolivia() / DisponibilidadService.
     */
    private static function ahoraBolivia(): Carbon
    {
        return Carbon::now(self::TIMEZONE_BOLIVIA);
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
