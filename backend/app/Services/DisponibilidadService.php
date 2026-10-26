<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\SolicitudReservaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula la grilla de disponibilidad horaria de un campo para una fecha.
 *
 * Decisión de diseño (Documento 1 v3, punto abierto 1):
 *   Bloques fijos de 1 hora por defecto. Si el Product Owner confirma
 *   otro tamaño, el cambio queda aislado en este service.
 *
 * La grilla es INFORMATIVA: la garantía anti-doble-reserva sigue siendo
 * la restricción EXCLUDE a nivel BD, no este endpoint.
 *
 * [FIX #1 grilla] Además de 'libre'/'ocupada'/'bloqueada_temporal', la grilla
 * puede devolver 'pasada': un bloque cuyo hora_inicio ya corrió según el
 * reloj oficial de Bolivia, cuando la fecha consultada es HOY. Es un estado
 * DERIVADO DEL TIEMPO (no de una solicitud) que sirve para que el frontend
 * no ofrezca franjas que el backend rechazaría con 422 en
 * SolicitudReservaService::validarFranjaFutura(). Ambos usan el mismo tz y
 * la misma regla (inicio <= ahora), así grilla y gate están siempre alineados.
 */
class DisponibilidadService
{
    public const TAMANO_BLOQUE_MINUTOS = 60;

    // [FIX #1 grilla] Mantener en sync con SolicitudReservaService::TIMEZONE_BOLIVIA.
    // TODO: extraer a un helper común (App\Support\FechaBolivia) para no duplicar
    // el tz en dos services.
    private const TIMEZONE_BOLIVIA = 'America/La_Paz';

    /**
     * Estados de solicitud que ocupan/bloquean una franja de la grilla.
     * v2.0.0: se redujo de 4 a 2 valores (los otros estados se fueron
     * al Core de Recaudaciones y no existen en la columna denormalizada).
     */
    private const ESTADOS_ACTIVOS = [
        EstadoSolicitudReserva::Pendiente->value,
        EstadoSolicitudReserva::Confirmada->value,
    ];

    /**
     * @return array{abierto: bool, fecha: string, bloques: array}
     */
    public function calcularGrilla(CampoDeportivo $campo, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();

        // ── Paso 1: buscar el horario de atención para el día consultado ──
        $horario = $campo->horariosAtencion()
            ->where('dia_semana', $fecha->dayOfWeekIso)
            ->first();

        if (! $horario) {
            return [
                'abierto' => false,
                'fecha' => $fecha->toDateString(),
                'bloques' => [],
            ];
        }

        // ── Paso 2: generar bloques de 1 hora entre apertura y cierre ──
        $bloques = $this->generarBloques($horario->hora_apertura, $horario->hora_cierre);

        // ── Paso 3: consultar filas activas de solicitud_reserva_detalle ──
        $solicitudesActivas = SolicitudReservaDetalle::query()
            ->where('campo_id', $campo->id)
            ->where('fecha_reserva', $fecha->toDateString())
            ->whereIn('estado_solicitud', self::ESTADOS_ACTIVOS)
            ->get(['hora_inicio', 'hora_fin', 'estado_solicitud']);

        // P8.2B: si el campo tiene varias variantes/servicios espejados, la
        // grilla física no puede mostrar un único precio. El precio depende
        // de la variante seleccionada por el funcionario/ciudadano.
        //
        // [FIX B78-2] usar el scope paraGrillaHoraria() (igual que
        // SolicitudReservaService::verificarFranjas) para no traer tarifas
        // vencidas / no-liquidable / con perfil (piscina) mezcladas.
        $tarifasGrilla = $campo->tarifas()->paraGrillaHoraria()->get();

        $serviciosDistintos = $tarifasGrilla
            ->pluck('servicio_sireb_id')
            ->filter()
            ->unique()
            ->count();

        // [FIX B78-1] ANTES: $tarifasGrilla->first()?->precio_por_hora → un ÚNICO
        // precio (el de la primera tarifa, casi siempre la diurna) asignado a TODOS
        // los bloques. Por eso el estimado legacy mentía: trinidad 09+19 daba
        // 50×2=100 en vez de 50+150=200; sintética 12+21 daba 50×2=100 en vez de
        // 50+100=150. El COBRO real estaba bien (resolverTarifaParaFranja clasifica
        // por hora); lo que fallaba era la transparencia del estimado y el precio
        // que ve el ciudadano en la grilla pública (misma fuente).
        //
        // AHORA: el precio se resuelve POR BLOQUE con la MISMA regla del cobro
        // (unica → esa; si no, diurna/nocturna según hora_inicio vs hora_inicio_noche).
        // Así grilla y gate de cobro quedan alineados (semántica idéntica a 1043).
        $inicioNocheSeg = self::aSegundos($campo->hora_inicio_noche ?? '18:00:00');

        $resolverPrecioBloque = function (array $bloque) use ($tarifasGrilla, $serviciosDistintos, $inicioNocheSeg): ?float {
            // Campo con múltiples servicios espejados: el precio depende de la
            // variante, no de la grilla. null → el front (camino variante) usa
            // las tarifas de la variante, que sí traen clasificacion.
            if ($serviciosDistintos > 1) {
                return null;
            }

            if ($tarifasGrilla->isEmpty()) {
                return null;
            }

            $unica = $tarifasGrilla->firstWhere('clasificacion', 'unica');
            if ($unica) {
                return (float) $unica->precio_por_hora;
            }

            $tipo = self::aSegundos($bloque['hora_inicio']) < $inicioNocheSeg ? 'diurna' : 'nocturna';
            $por = $tarifasGrilla->firstWhere('clasificacion', $tipo);

            return $por ? (float) $por->precio_por_hora : null;
        };

        // [FIX #1 grilla] ¿La fecha pedida es HOY en hora Bolivia? Si sí,
        // los bloques cuyo inicio ya corrió se marcan 'pasada'.
        $ahoraBolivia = Carbon::now(self::TIMEZONE_BOLIVIA);
        $esHoyBolivia = $fecha->toDateString() === $ahoraBolivia->toDateString();
        $ahoraSeg = $esHoyBolivia ? self::aSegundos($ahoraBolivia->format('H:i:s')) : -1;

        // ── Paso 4: etiquetar cada bloque cruzando con las solicitudes ──
        $bloques = $bloques->map(function ($bloque) use ($solicitudesActivas, $resolverPrecioBloque, $esHoyBolivia, $ahoraSeg) {
            $estado = ($esHoyBolivia && self::aSegundos($bloque['hora_inicio']) <= $ahoraSeg)
                ? 'pasada'
                : $this->calcularEstadoDelBloque($bloque, $solicitudesActivas);

            return [
                'hora_inicio' => $bloque['hora_inicio'],
                'hora_fin' => $bloque['hora_fin'],
                'estado' => $estado,
                'precio' => $resolverPrecioBloque($bloque),
            ];
        })->all();

        return [
            'abierto' => true,
            'fecha' => $fecha->toDateString(),
            'bloques' => $bloques,
        ];
    }

    /**
     * Genera bloques de 1 hora entre hora_apertura y hora_cierre.
     *
     * Ejemplo: '08:00' - '20:00' → 12 bloques de 08-09, 09-10, ..., 19-20.
     */
    private function generarBloques(string $horaApertura, string $horaCierre): Collection
    {
        $bloques = collect();
        $inicio = Carbon::createFromFormat('H:i:s', $horaApertura);
        $fin = Carbon::createFromFormat('H:i:s', $horaCierre);

        while ($inicio->copy()->addMinutes(self::TAMANO_BLOQUE_MINUTOS)->lte($fin)) {
            $finBloque = $inicio->copy()->addMinutes(self::TAMANO_BLOQUE_MINUTOS);

            $bloques->push([
                'hora_inicio' => $inicio->format('H:i:s'),
                'hora_fin' => $finBloque->format('H:i:s'),
            ]);

            $inicio = $finBloque;
        }

        return $bloques;
    }

    /**
     * Determina el estado de un bloque cruzándolo con las solicitudes activas.
     *
     * - 'libre': sin superposición con ninguna solicitud activa.
     * - 'ocupada': superposición con al menos una solicitud 'confirmada'.
     * - 'bloqueada_temporal': superposición con 'pendiente' (sin 'confirmada' previa).
     *
     * Nota: 'pasada' NO se calcula acá; se aplica antes en calcularGrilla
     * porque depende del reloj, no de las solicitudes.
     */
    private function calcularEstadoDelBloque(array $bloque, Collection $solicitudes): string
    {
        $bloqueInicio = Carbon::createFromFormat('H:i:s', $bloque['hora_inicio']);
        $bloqueFin = Carbon::createFromFormat('H:i:s', $bloque['hora_fin']);

        $confirmada = false;
        $pendiente = false;

        foreach ($solicitudes as $s) {
            $sInicio = Carbon::createFromFormat('H:i:s', $s->hora_inicio);
            $sFin = Carbon::createFromFormat('H:i:s', $s->hora_fin);

            // Dos intervalos se superponen si y solo si:
            //   inicio_A < fin_B AND fin_A > inicio_B
            if ($bloqueInicio->lt($sFin) && $bloqueFin->gt($sInicio)) {
                if ($s->estado_solicitud === EstadoSolicitudReserva::Confirmada->value) {
                    $confirmada = true;
                } elseif ($s->estado_solicitud === EstadoSolicitudReserva::Pendiente->value) {
                    $pendiente = true;
                }
            }
        }

        if ($confirmada) return 'ocupada';
        if ($pendiente) return 'bloqueada_temporal';
        return 'libre';
    }

    // [FIX #1 grilla] Mismo helper que SolicitudReservaService::aSegundos.
    // TODO: unificar en el helper común de tz/fechas.
    private static function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }
}
