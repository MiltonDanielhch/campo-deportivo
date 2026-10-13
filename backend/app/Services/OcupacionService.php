<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Reserva;
use App\Models\SolicitudReservaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\SolicitudReserva;
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
     * Verifica un código de reserva. Acepta AMBOS formatos:
     *   - RSV-… (codigo_reserva, 1 franja)  → lo que muestra la grilla del panel
     *   - RES-… (codigo_seguimiento, N franjas) → lo que trae el QR del comprobante
     *
     * Regla de seguridad: si el funcionario es funcionario_control y el campo
     * no está entre sus asignados, responde null (→ 404 uniforme) igual que si
     * el código no existiera. Para RES- multi-franja, filtra SOLO las franjas
     * de sus campos: no expone existencia ni datos de franjas ajenas.
     *
     * [FIX #2a] Expone el titular (nombre + CI/NIT + teléfono) al funcionario
     * autenticado con acceso, porque la verificación de identidad en sitio
     * exige cruzar el carnet. El endpoint PÚBLICO (SolicitudEstadoResource)
     * sigue SIN exponerlos; este es el canal interno protegido.
     *
     * @return array<string, mixed>|null null si no existe o no tiene acceso.
     */
    public function verificarCodigo(string $codigo, Funcionario $funcionario): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        $esAdmin = $this->esAdministrador($funcionario);

        // ── Camino A: código de franja (RSV-…) ──
        $reserva = Reserva::with(['solicitud', 'campo', 'detalle'])
            ->where('codigo_reserva', $codigo)
            ->first();

        if ($reserva) {
            if ($reserva->solicitud->estado !== EstadoSolicitudReserva::Confirmada) {
                return null;
            }
            if (! $esAdmin && ! $this->campoAsignado($funcionario, $reserva->campo_id)) {
                return null;
            }

            return $this->armarResultadoVerificacion($reserva->solicitud, $reserva->campo, [$reserva]);
        }

        // ── Camino B: código de seguimiento (RES-…) → solicitud con N franjas ──
        $solicitud = SolicitudReserva::with(['detalles.reserva.campo'])
            ->where('codigo_seguimiento', $codigo)
            ->where('estado', EstadoSolicitudReserva::Confirmada)
            ->first();

        if (! $solicitud) {
            return null;
        }

        /** @var Collection<int, Reserva> $reservas */
        $reservas = $solicitud->detalles
            ->pluck('reserva')
            ->filter()
            ->values();

        if ($reservas->isEmpty()) {
            return null;
        }

        // Filtro por asignación para funcionario_control: solo sus franjas.
        if (! $esAdmin) {
            $reservas = $reservas
                ->filter(fn (Reserva $r) => $this->campoAsignado($funcionario, $r->campo_id))
                ->values();

            if ($reservas->isEmpty()) {
                return null; // sin acceso a NINGUNA franja → 404 uniforme
            }
        }

        $primerCampo = $reservas->first()?->campo;

        return $this->armarResultadoVerificacion($solicitud, $primerCampo, $reservas->all());
    }

    /**
     * Arma la respuesta unificada del verificador (1 o N franjas).
     * Cada franja incluye `id` (PK de reservas) porque el POST de asistencia
     * (/admin/reservas/{id}/asistencia) la requiere, no el codigo_reserva.
     *
     * @param  array<int, Reserva>  $reservas
     */
    private function armarResultadoVerificacion(
        SolicitudReserva $solicitud,
        ?CampoDeportivo $campoReferencia,
        array $reservas,
    ): array {
        return [
            'codigo_seguimiento' => $solicitud->codigo_seguimiento,
            'estado_solicitud'   => $solicitud->estado->value,
            'titular' => [
                'nombre'   => $solicitud->nombre_pagador,
                'ci_nit'   => $solicitud->ci_nit_pagador,
                'telefono' => $solicitud->telefono_pagador,
            ],
            'campo_id'      => $campoReferencia?->id,
            'campo_nombre'  => $campoReferencia?->nombre,
            'fecha_reserva' => $reservas[0]->fecha_reserva?->toDateString(),
            'franjas' => array_map(fn (Reserva $r) => [
                'id' => $r->id,
                'codigo_reserva'        => $r->codigo_reserva,
                'campo_nombre'          => $r->campo?->nombre,
                'fecha'                 => $r->fecha_reserva?->toDateString(),
                'hora_inicio'           => substr((string) $r->hora_inicio, 0, 5),
                'hora_fin'              => substr((string) $r->hora_fin, 0, 5),
                'asistencia_marcada_en' => $r->asistencia_marcada_en?->toIso8601String(),
            ], $reservas),
        ];
    }

        /**
     * Obtiene todos los campos activos o en mantenimiento con su ocupación del día.
     * Sin filtro por asignación — solo para admin_parametricas y gerencia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mapaGlobal(Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();

        $campos = CampoDeportivo::query()
            ->whereIn('estado', ['activo', 'mantenimiento'])
            ->with(['horariosAtencion', 'tarifas' => fn ($q) => $q->whereNull('vigente_hasta')])
            ->orderBy('nombre')
            ->get();

        return $campos->map(function (CampoDeportivo $campo) use ($fecha) {
            $ocupacion = $this->ocupacionDelCampo($campo, $fecha);

            // Agregar coordenadas y metadatos administrativos
            $franjas = $ocupacion['franjas'];

            $franjasTotales = count($franjas);
            $franjasOcupadas = count(array_filter($franjas, fn ($f) => $f['estado'] === 'ocupada'));
            $franjasPendientes = count(array_filter($franjas, fn ($f) => $f['estado'] === 'pendiente'));
            $franjasLibres = count(array_filter($franjas, fn ($f) => $f['estado'] === 'libre'));

            $porcentajeOcupacion = $franjasTotales > 0
                ? round(($franjasOcupadas / $franjasTotales) * 100, 1)
                : 0;

            $ocupadoAhora = $this->estaOcupadoAhora($franjas, $fecha);

            return array_merge($ocupacion, [
                'latitud' => (float) $campo->latitud,
                'longitud' => (float) $campo->longitud,
                'direccion' => $campo->direccion,
                'estado_operativo' => $campo->estado,
                'vinculacion_sireb' => [
                    'vinculado' => $campo->servicio_sireb_id !== null,
                    'servicio_sireb_id' => $campo->servicio_sireb_id,
                    'servicio_sireb_codigo' => $campo->servicio_sireb_codigo,
                ],
                'resumen_ocupacion' => [
                    'franjas_totales' => $franjasTotales,
                    'franjas_ocupadas' => $franjasOcupadas,
                    'franjas_pendientes' => $franjasPendientes,
                    'franjas_libres' => $franjasLibres,
                    'porcentaje_ocupacion' => $porcentajeOcupacion,
                    'ocupado_ahora' => $ocupadoAhora,
                ],
            ]);
        })->values()->all();
    }

    /**
     * Determina si el campo está ocupado en este momento.
     *
     * @param array<int, array<string, mixed>> $franjas
     */
    private function estaOcupadoAhora(array $franjas, Carbon $fecha): bool
    {
        $hoy = now();

        // Solo aplica si la fecha consultada es hoy
        if (! $hoy->isSameDay($fecha)) {
            return false;
        }

        $ahoraSegundos = (int) $hoy->format('H') * 3600
            + (int) $hoy->format('i') * 60
            + (int) $hoy->format('s');

        foreach ($franjas as $franja) {
            if ($franja['estado'] !== 'ocupada') {
                continue;
            }

            $inicio = Carbon::createFromFormat('H:i:s', $franja['hora_inicio']);
            $fin = Carbon::createFromFormat('H:i:s', $franja['hora_fin']);

            $inicioSegundos = (int) $inicio->format('H') * 3600
                + (int) $inicio->format('i') * 60
                + (int) $inicio->format('s');

            $finSegundos = (int) $fin->format('H') * 3600
                + (int) $fin->format('i') * 60
                + (int) $fin->format('s');

            if ($ahoraSegundos >= $inicioSegundos && $ahoraSegundos < $finSegundos) {
                return true;
            }
        }

        return false;
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
