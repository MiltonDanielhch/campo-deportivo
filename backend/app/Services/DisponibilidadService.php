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
        // dayOfWeekIso: 1=lunes ... 7=domingo (coincide con ISO-8601 de la BD)
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

        // Tarifa vigente para calcular el precio de cada bloque.
        // El controller la carga con whereNull('vigente_hasta').
        $precioPorHora = $campo->tarifas->first()?->precio_por_hora;
        $precioFloat = $precioPorHora !== null ? (float) $precioPorHora : null;

        // [FIX #1 grilla] ¿La fecha pedida es HOY en hora Bolivia? Si sí,
        // los bloques cuyo inicio ya corrió se marcan 'pasada'. Comparamos
        // día calendario (toDateString) para que no importe cómo se parseó
        // $fecha en el caller: el día es el día.
        $ahoraBolivia = Carbon::now(self::TIMEZONE_BOLIVIA);
        $esHoyBolivia = $fecha->toDateString() === $ahoraBolivia->toDateString();
        $ahoraSeg = $esHoyBolivia ? self::aSegundos($ahoraBolivia->format('H:i:s')) : -1;

        // ── Paso 4: etiquetar cada bloque cruzando con las solicitudes ──
        $bloques = $bloques->map(function ($bloque) use ($solicitudesActivas, $precioFloat, $esHoyBolivia, $ahoraSeg) {
            // El gate temporal tiene prioridad: una franja de hoy que ya
            // comenzó es 'pasada' aunque estuviera libre/ocupada/en cobro.
            $estado = ($esHoyBolivia && self::aSegundos($bloque['hora_inicio']) <= $ahoraSeg)
                ? 'pasada'
                : $this->calcularEstadoDelBloque($bloque, $solicitudesActivas);

            return [
                'hora_inicio' => $bloque['hora_inicio'],
                'hora_fin' => $bloque['hora_fin'],
                'estado' => $estado,
                'precio' => $precioFloat,
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
