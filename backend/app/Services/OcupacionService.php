<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Reserva;
use App\Models\SolicitudReservaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Servicio de ocupación para control en sitio (HU-F1).
 *
 * Diferencia con DisponibilidadService:
 * - DisponibilidadService: vista pública, ciudadano ve qué está libre.
 * - OcupacionService: vista interna, funcionario ve qué está ocupado en sus campos.
 *
 * Regla de seguridad: funcionario_control solo ve campos asignados (server-side).
 * No expone datos personales del pagador (nombre_pagador, telefono_pagador).
 */
class OcupacionService
{
    public const TAMANO_BLOQUE_MINUTOS = 60;

    /**
     * Estados de solicitud que ocupan una franja.
     * Mismo criterio que DisponibilidadService (columna denormalizada estado_solicitud).
     */
    private const ESTADOS_ACTIVOS = [
        EstadoSolicitudReserva::Pendiente->value,
        EstadoSolicitudReserva::Confirmada->value,
    ];

    /**
     * Obtiene los campos asignados al funcionario con su ocupación del día.
     *
     * @return array<int, array<string, mixed>>
     */
    public function misCampos(Funcionario $funcionario, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();

        $campos = $this->obtenerCamposAsignados($funcionario);

        return $campos->map(function (CampoDeportivo $campo) use ($fecha) {
            return $this->ocupacionDelCampo($campo, $fecha);
        })->values()->all();
    }

    /**
     * Verifica un código de reserva.
     *
     * Regla de seguridad: si el funcionario es funcionario_control y el campo
     * no está entre sus asignados, responde 404 igual que si el código no existiera.
     * Esto evita filtrar existencia de reservas en campos ajenos.
     *
     * @return array<string, mixed>|null Devuelve null si no existe o no tiene acceso.
     */
    public function verificarCodigo(string $codigoReserva, Funcionario $funcionario): ?array
    {
        $reserva = Reserva::with(['solicitud', 'campo', 'detalle'])
            ->where('codigo_reserva', $codigoReserva)
            ->first();

        if (! $reserva) {
            return null;
        }

        // Verificar que la solicitud esté confirmada
        if ($reserva->solicitud->estado !== EstadoSolicitudReserva::Confirmada) {
            return null;
        }

        // Restricción server-side para funcionario_control
        if (! $this->esAdministrador($funcionario)) {
            $asignado = $this->campoAsignado($funcionario, $reserva->campo_id);

            if (! $asignado) {
                // Responder 404 uniforme, no filtrar existencia
                return null;
            }
        }

        return [
            'codigo_reserva' => $reserva->codigo_reserva,
            'campo_id' => $reserva->campo_id,
            'campo_nombre' => $reserva->campo?->nombre,
            'fecha_reserva' => $reserva->fecha_reserva?->toDateString(),
            'hora_inicio' => substr((string) $reserva->hora_inicio, 0, 5),
            'hora_fin' => substr((string) $reserva->hora_fin, 0, 5),
            'asistencia_marcada_en' => $reserva->asistencia_marcada_en?->toIso8601String(),
            // NO exponer: nombre_pagador, telefono_pagador, ci_nit_pagador
        ];
    }

    /**
     * Obtiene campos asignados al funcionario.
     * Si es admin, devuelve todos los campos activos.
     */
    private function obtenerCamposAsignados(Funcionario $funcionario): Collection
    {
        if ($this->esAdministrador($funcionario)) {
            return CampoDeportivo::query()
                ->where('estado', 'activo')
                ->with(['horariosAtencion', 'tarifas' => fn ($q) => $q->whereNull('vigente_hasta')])
                ->get();
        }

        // funcionario_control: solo campos asignados
        return $funcionario->camposAsignados()
            ->where('estado', 'activo')
            ->with(['horariosAtencion', 'tarifas' => fn ($q) => $q->whereNull('vigente_hasta')])
            ->get();
    }

    /**
     * Calcula la ocupación de un campo para una fecha.
     *
     * @return array<string, mixed>
     */
    private function ocupacionDelCampo(CampoDeportivo $campo, Carbon $fecha): array
    {
        $horario = $campo->horariosAtencion()
            ->where('dia_semana', $fecha->dayOfWeekIso)
            ->first();

        if (! $horario) {
            return [
                'campo_id' => $campo->id,
                'campo_nombre' => $campo->nombre,
                'abierto' => false,
                'fecha' => $fecha->toDateString(),
                'franjas' => [],
            ];
        }

        $franjas = $this->generarFranjas($horario->hora_apertura, $horario->hora_cierre);

        $solicitudesActivas = SolicitudReservaDetalle::query()
            ->where('campo_id', $campo->id)
            ->where('fecha_reserva', $fecha->toDateString())
            ->whereIn('estado_solicitud', self::ESTADOS_ACTIVOS)
            ->with(['solicitud', 'reserva'])
            ->get();

        $franjas = $franjas->map(function ($franja) use ($solicitudesActivas) {
            $estado = $this->calcularEstadoFranja($franja, $solicitudesActivas);

            return [
                'hora_inicio' => $franja['hora_inicio'],
                'hora_fin' => $franja['hora_fin'],
                'estado' => $estado['estado'],
                'codigo_reserva' => $estado['codigo_reserva'],
                'asistencia_marcada_en' => $estado['asistencia_marcada_en'],
            ];
        })->all();

        return [
            'campo_id' => $campo->id,
            'campo_nombre' => $campo->nombre,
            'abierto' => true,
            'fecha' => $fecha->toDateString(),
            'franjas' => $franjas,
        ];
    }

    /**
     * Genera franjas de 1 hora entre hora_apertura y hora_cierre.
     */
    private function generarFranjas(string $horaApertura, string $horaCierre): Collection
    {
        $franjas = collect();
        $inicio = Carbon::createFromFormat('H:i:s', $horaApertura);
        $fin = Carbon::createFromFormat('H:i:s', $horaCierre);

        while ($inicio->copy()->addMinutes(self::TAMANO_BLOQUE_MINUTOS)->lte($fin)) {
            $finFranja = $inicio->copy()->addMinutes(self::TAMANO_BLOQUE_MINUTOS);

            $franjas->push([
                'hora_inicio' => $inicio->format('H:i:s'),
                'hora_fin' => $finFranja->format('H:i:s'),
            ]);

            $inicio = $finFranja;
        }

        return $franjas;
    }

    /**
     * Determina el estado de una franja cruzándola con solicitudes activas.
     *
     * @return array{estado: string, codigo_reserva: string|null, asistencia_marcada_en: string|null}
     */
    private function calcularEstadoFranja(array $franja, Collection $solicitudes): array
    {
        $franjaInicio = Carbon::createFromFormat('H:i:s', $franja['hora_inicio']);
        $franjaFin = Carbon::createFromFormat('H:i:s', $franja['hora_fin']);

        $confirmada = false;
        $pendiente = false;
        $codigoReserva = null;
        $asistenciaMarcadaEn = null;

        foreach ($solicitudes as $s) {
            $sInicio = Carbon::createFromFormat('H:i:s', $s->hora_inicio);
            $sFin = Carbon::createFromFormat('H:i:s', $s->hora_fin);

            // Dos intervalos se superponen si: inicio_A < fin_B AND fin_A > inicio_B
            if ($franjaInicio->lt($sFin) && $franjaFin->gt($sInicio)) {
                if ($s->estado_solicitud === EstadoSolicitudReserva::Confirmada->value) {
                    $confirmada = true;

                    // Si hay reserva confirmada, capturar código y asistencia
                    if ($s->reserva) {
                        $codigoReserva = $s->reserva->codigo_reserva;
                        $asistenciaMarcadaEn = $s->reserva->asistencia_marcada_en?->toIso8601String();
                    }
                } elseif ($s->estado_solicitud === EstadoSolicitudReserva::Pendiente->value) {
                    $pendiente = true;
                }
            }
        }

        if ($confirmada) {
            return [
                'estado' => 'ocupada',
                'codigo_reserva' => $codigoReserva,
                'asistencia_marcada_en' => $asistenciaMarcadaEn,
            ];
        }

        if ($pendiente) {
            return [
                'estado' => 'pendiente',
                'codigo_reserva' => null,
                'asistencia_marcada_en' => null,
            ];
        }

        return [
            'estado' => 'libre',
            'codigo_reserva' => null,
            'asistencia_marcada_en' => null,
        ];
    }

    private function esAdministrador(Funcionario $funcionario): bool
    {
        return $funcionario->tienePermiso('*')
            || in_array(
                $funcionario->rol?->nombre,
                ['admin_parametricas', 'admin_reservas'],
                true
            );
    }

    private function campoAsignado(Funcionario $funcionario, string $campoId): bool
    {
        return $funcionario->camposAsignados()
            ->where('campos_deportivos.id', $campoId)
            ->exists();
    }
}
