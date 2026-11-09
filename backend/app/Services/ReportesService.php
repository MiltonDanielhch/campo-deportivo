<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\Reserva;
use App\Models\SolicitudReserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de reportes gerenciales (HU-F2 y HU-F3).
 *
 * IMPORTANTE: Estos reportes son la VISTA OPERATIVA de Canchas.
 * NO reemplazan la conciliación financiera oficial de Paitití/SIREB,
 * que es la fuente autoritativa de liquidaciones y pagos.
 *
 * Todos los reportes solo cuentan solicitudes/reservas CONFIRMADAS.
 * Estados pendiente, expirada, cancelada y rechazada NO se incluyen.
 */
class ReportesService
{
    /**
     * Nota obligatoria en todos los reportes.
     * Paitití/SIREB es la fuente autoritativa de conciliación financiera.
     */
    public const NOTA_VISTA_OPERATIVA = 'Estos valores son una vista operativa del sistema de canchas. '
        . 'La conciliación financiera oficial corresponde a Paitití / SIREB.';

    /**
     * Ingresos por campo: suma de reservas.monto_pagado agrupado por campo_id.
     *
     * Usa el índice idx_reservas_campo_fecha (compuesto: campo_id + fecha_reserva).
     *
     * @return array<int, array<string, mixed>>
     */
    public function ingresosPorCampo(Carbon $desde, Carbon $hasta, ?string $campoId = null): array
    {
        $query = Reserva::query()
            ->join('solicitudes_reserva', 'reservas.solicitud_reserva_id', '=', 'solicitudes_reserva.id')
            ->join('campos_deportivos', 'reservas.campo_id', '=', 'campos_deportivos.id')
            ->where('solicitudes_reserva.estado', EstadoSolicitudReserva::Confirmada)
            ->whereBetween('reservas.fecha_reserva', [
                $desde->toDateString(),
                $hasta->toDateString(),
            ]);

        if ($campoId) {
            $query->where('reservas.campo_id', $campoId);
        }

        return $query
            ->select(
                'reservas.campo_id',
                'campos_deportivos.nombre as campo_nombre',
                'campos_deportivos.codigo as campo_codigo',
                DB::raw('SUM(reservas.monto_pagado) as total_ingresos'),
                DB::raw('COUNT(reservas.id) as total_reservas')
            )
            ->groupBy('reservas.campo_id', 'campos_deportivos.nombre', 'campos_deportivos.codigo')
            ->orderByDesc('total_ingresos')
            ->get()
            ->map(fn ($row) => [
                'campo_id' => $row->campo_id,
                'campo_nombre' => $row->campo_nombre,
                'campo_codigo' => $row->campo_codigo,
                'total_ingresos' => (float) $row->total_ingresos,
                'total_reservas' => (int) $row->total_reservas,
            ])
            ->all();
    }

    /**
     * Histograma de horas pico: conteo de reservas agrupado por la hora de hora_inicio.
     *
     * @return array<int, array{hora: int, total_reservas: int}>
     */
    public function histogramaHorasPico(Carbon $desde, Carbon $hasta): array
    {
        $resultados = Reserva::query()
            ->join('solicitudes_reserva', 'reservas.solicitud_reserva_id', '=', 'solicitudes_reserva.id')
            ->where('solicitudes_reserva.estado', EstadoSolicitudReserva::Confirmada)
            ->whereBetween('reservas.fecha_reserva', [
                $desde->toDateString(),
                $hasta->toDateString(),
            ])
            ->select(
                DB::raw('EXTRACT(HOUR FROM reservas.hora_inicio)::int as hora'),
                DB::raw('COUNT(reservas.id) as total_reservas')
            )
            ->groupBy('hora')
            ->orderBy('hora')
            ->get();

        // Completar las 24 horas con ceros para que el histograma sea completo
        $histograma = [];
        for ($hora = 0; $hora < 24; $hora++) {
            $encontrado = $resultados->firstWhere('hora', $hora);

            $histograma[] = [
                'hora' => $hora,
                'total_reservas' => $encontrado ? (int) $encontrado->total_reservas : 0,
            ];
        }

        return $histograma;
    }

    /**
     * Clientes frecuentes: agrupación por CI/NIT normalizado, o teléfono si no hay CI/NIT.
     *
     * Solo cuenta solicitudes CONFIRMADAS. El nombre mostrado es el más reciente
     * para manejar casos donde el mismo CI/NIT tiene nombres escritos distintos.
     *
     * Excluye registros sin CI/NIT ni teléfono util: no son un cliente identificable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function clientesFrecuentes(Carbon $desde, Carbon $hasta, int $limite = 50): array
    {
        $clave = "COALESCE(NULLIF(TRIM(ci_nit_pagador), ''), NULLIF(TRIM(telefono_pagador), ''))";

        return SolicitudReserva::query()
            ->where('estado', EstadoSolicitudReserva::Confirmada)
            ->whereBetween('creado_en', [
                $desde->startOfDay(),
                $hasta->endOfDay(),
            ])
            ->selectRaw("{$clave} as clave_agrupacion")
            ->selectRaw('COUNT(*) as total_reservas')
            ->selectRaw('SUM(monto_total) as monto_total_gastado')
            ->selectRaw('(ARRAY_AGG(nombre_pagador ORDER BY creado_en DESC))[1] as nombre_mas_reciente')
            ->groupByRaw($clave)
            ->havingRaw("{$clave} IS NOT NULL")
            ->orderByDesc('total_reservas')
            ->orderByDesc('monto_total_gastado')
            ->limit($limite)
            ->get()
            ->map(fn ($row) => [
                'clave_agrupacion' => $row->clave_agrupacion,
                'nombre_mas_reciente' => $row->nombre_mas_reciente,
                'total_reservas' => (int) $row->total_reservas,
                'monto_total_gastado' => (float) $row->monto_total_gastado,
            ])
            ->all();
    }

    /**
     * Total de clientes únicos confirmados en el rango, independientemente del límite del top.
     *
     * Usa la misma clave normalizada que clientesFrecuentes(). COUNT(DISTINCT ...)
     * ignifica NULL automáticamente, así que los registros sin CI/NIT ni teléfono
     * no entran en el total.
     */
    public function totalClientesUnicos(Carbon $desde, Carbon $hasta): int
    {
        $clave = "COALESCE(NULLIF(TRIM(ci_nit_pagador), ''), NULLIF(TRIM(telefono_pagador), ''))";

        $total = SolicitudReserva::query()
            ->where('estado', EstadoSolicitudReserva::Confirmada)
            ->whereBetween('creado_en', [
                $desde->startOfDay(),
                $hasta->endOfDay(),
            ])
            ->selectRaw("COUNT(DISTINCT {$clave}) as total")
            ->value('total');

        return (int) $total;
    }
}
