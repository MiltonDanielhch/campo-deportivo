<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Reserva;
use App\Models\SolicitudReserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de Dashboard principal (resumen del día).
 *
 * Provee KPIs del día actual adaptados al rol del usuario.
 */
class DashboardService
{
    /**
     * Obtiene el resumen del día actual.
     *
     * @return array<string, mixed>
     */
    public function resumenDelDia(Funcionario $funcionario): array
    {
        $hoy = Carbon::today();
        $ahora = Carbon::now();

        // KPIs básicos
        $reservasHoy = $this->contarReservasHoy($hoy);
        $camposOcupadosAhora = $this->contarCamposOcupadosAhora($hoy, $ahora);
        $ingresosHoy = $this->sumarIngresosHoy($hoy);
        $totalCamposActivos = $this->contarCamposActivos();

        // Distribución por hora del día
        $distribucionHoy = $this->distribucionPorHora($hoy);

        // Accesos rápidos según rol
        $accesosRapidos = $this->obtenerAccesosRapidos($funcionario);

        return [
            'fecha' => $hoy->toDateString(),
            'reservas_hoy' => $reservasHoy,
            'campos_ocupados_ahora' => $camposOcupadosAhora,
            'ingresos_hoy' => $ingresosHoy,
            'total_campos_activos' => $totalCamposActivos,
            'distribucion_hoy' => $distribucionHoy,
            'accesos_rapidos' => $accesosRapidos,
        ];
    }

    private function contarReservasHoy(Carbon $fecha): int
    {
        return Reserva::query()
            ->join('solicitudes_reserva', 'reservas.solicitud_reserva_id', '=', 'solicitudes_reserva.id')
            ->where('solicitudes_reserva.estado', EstadoSolicitudReserva::Confirmada)
            ->whereDate('reservas.fecha_reserva', $fecha)
            ->count();
    }

    private function contarCamposOcupadosAhora(Carbon $fecha, Carbon $ahora): int
    {
        $horaActual = $ahora->format('H:i:s');

        return Reserva::query()
            ->join('solicitudes_reserva', 'reservas.solicitud_reserva_id', '=', 'solicitudes_reserva.id')
            ->where('solicitudes_reserva.estado', EstadoSolicitudReserva::Confirmada)
            ->whereDate('reservas.fecha_reserva', $fecha)
            ->whereTime('reservas.hora_inicio', '<=', $horaActual)
            ->whereTime('reservas.hora_fin', '>', $horaActual)
            ->distinct('reservas.campo_id')
            ->count('reservas.campo_id');
    }

    private function sumarIngresosHoy(Carbon $fecha): float
    {
        $total = Reserva::query()
            ->join('solicitudes_reserva', 'reservas.solicitud_reserva_id', '=', 'solicitudes_reserva.id')
            ->where('solicitudes_reserva.estado', EstadoSolicitudReserva::Confirmada)
            ->whereDate('reservas.fecha_reserva', $fecha)
            ->sum('reservas.monto_pagado');

        return (float) $total;
    }

    private function contarCamposActivos(): int
    {
        return CampoDeportivo::query()
            ->where('estado', 'activo')
            ->count();
    }

    /**
     * Distribución de reservas de hoy por hora.
     *
     * @return array<int, array{hora: int, total: int}>
     */
    private function distribucionPorHora(Carbon $fecha): array
    {
        $resultados = Reserva::query()
            ->join('solicitudes_reserva', 'reservas.solicitud_reserva_id', '=', 'solicitudes_reserva.id')
            ->where('solicitudes_reserva.estado', EstadoSolicitudReserva::Confirmada)
            ->whereDate('reservas.fecha_reserva', $fecha)
            ->select(
                DB::raw('EXTRACT(HOUR FROM reservas.hora_inicio)::int as hora'),
                DB::raw('COUNT(reservas.id) as total')
            )
            ->groupBy('hora')
            ->orderBy('hora')
            ->get();

        // Completar las 24 horas con ceros
        $distribucion = [];
        for ($hora = 0; $hora < 24; $hora++) {
            $encontrado = $resultados->firstWhere('hora', $hora);

            $distribucion[] = [
                'hora' => $hora,
                'total' => $encontrado ? (int) $encontrado->total : 0,
            ];
        }

        return $distribucion;
    }

    /**
     * Accesos rápidos según el rol del usuario.
     *
     * @return array<int, string>
     */
    private function obtenerAccesosRapidos(Funcionario $funcionario): array
    {
        $rol = $funcionario->rol?->nombre;
        $accesos = [];

        switch ($rol) {
            case 'funcionario_control':
                $accesos = ['ocupacion', 'reservas'];
                break;

            case 'admin_reservas':
                $accesos = ['reservas', 'ocupacion'];
                break;

            case 'admin_parametricas':
            case 'gerencia':
                $accesos = ['reportes', 'mapa_global', 'ocupacion', 'reservas'];
                break;

            default:
                $accesos = ['ocupacion'];
        }

        return $accesos;
    }
}
