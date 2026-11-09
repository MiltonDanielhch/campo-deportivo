<?php

namespace App\Services;

use App\Enums\EstadoPiscinaReserva;
use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\PiscinaReserva;
use App\Models\PiscinaTurno;
use App\Models\Reserva;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OcupacionService
{
    public const TAMANO_BLOQUE_MINUTOS = 60;

    public const FRANJA_OCUPADA   = 'ocupada';
    public const FRANJA_PENDIENTE = 'pendiente';
    public const FRANJA_LIBRE     = 'libre';

    private const ESTADOS_ACTIVOS = [
        EstadoSolicitudReserva::Pendiente->value,
        EstadoSolicitudReserva::Confirmada->value,
    ];

    private const ROLES_ADMIN = ['admin_parametricas', 'admin_reservas', 'gerencia'];

    public function misCamposYTurnos(Funcionario $funcionario, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();
        $campos = $this->obtenerCamposAsignados($funcionario, incluirPiscina: true);

        return $campos->map(function (CampoDeportivo $campo) use ($fecha) {
            $base = [
                'campo_id'          => $campo->id,
                'campo_nombre'      => $campo->nombre,
                'modalidad_reserva' => $campo->modalidad_reserva,
                'fecha'             => $fecha->toDateString(),
            ];

            if ($this->esModalidadPiscina($campo->modalidad_reserva)) {
                $turnos = $this->turnosDelCampoPiscina($campo, $fecha);
                return array_merge($base, [
                    'abierto' => ! empty($turnos),
                    'turnos'  => $turnos,
                ]);
            }

            $ocupacion = $this->ocupacionDelCampo($campo, $fecha);
            return array_merge($base, [
                'abierto' => $ocupacion['abierto'],
                'franjas' => $ocupacion['franjas'],
            ]);
        })->values()->all();
    }

    public function misCampos(Funcionario $funcionario, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();
        $campos = $this->obtenerCamposAsignados($funcionario, incluirPiscina: false);

        return $campos->map(function (CampoDeportivo $campo) use ($fecha) {
            return $this->ocupacionDelCampo($campo, $fecha);
        })->values()->all();
    }

    public function verificarCodigo(string $codigo, Funcionario $funcionario): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        $esAdmin = $this->esAdministrador($funcionario);
        $idsAsignados = $esAdmin
            ? null
            : $funcionario->camposAsignados()->pluck('campos_deportivos.id')->all();

        $reserva = Reserva::with(['solicitud', 'campo', 'detalle'])
            ->where('codigo_reserva', $codigo)
            ->first();

        if ($reserva) {
            if ($reserva->solicitud->estado !== EstadoSolicitudReserva::Confirmada) {
                return null;
            }
            if (! $esAdmin && ! in_array($reserva->campo_id, $idsAsignados, true)) {
                return null;
            }
            return $this->armarResultadoVerificacion($reserva->solicitud, $reserva->campo, [$reserva]);
        }

        $solicitud = SolicitudReserva::with(['detalles.reserva.campo'])
            ->where('codigo_seguimiento', $codigo)
            ->where('estado', EstadoSolicitudReserva::Confirmada)
            ->first();

        if ($solicitud) {
            $reservas = $solicitud->detalles
                ->pluck('reserva')
                ->filter()
                ->values();

            if ($reservas->isEmpty()) {
                return null;
            }

            if (! $esAdmin) {
                $reservas = $reservas
                    ->filter(fn (Reserva $r) => in_array($r->campo_id, $idsAsignados, true))
                    ->values();

                if ($reservas->isEmpty()) {
                    return null;
                }
            }

            return $this->armarResultadoVerificacion(
                $solicitud,
                $reservas->first()?->campo,
                $reservas->all()
            );
        }

        return $this->verificarCodigoPiscina($codigo, $funcionario, $esAdmin, $idsAsignados);
    }

    private function verificarCodigoPiscina(
        string $codigo,
        Funcionario $funcionario,
        bool $esAdmin,
        ?array $idsAsignados,
    ): ?array {
        $reserva = PiscinaReserva::with(['detalles', 'campo'])
            ->where('estado', EstadoPiscinaReserva::Confirmada->value)
            ->where(function ($q) use ($codigo) {
                $q->where('codigo_seguimiento', $codigo)
                    ->orWhere('referencia_recaudaciones', $codigo);
            })
            ->first();

        if (! $reserva) {
            return null;
        }

        if (! $esAdmin && ! in_array($reserva->campo_id, $idsAsignados ?? [], true)) {
            return null;
        }

        return $this->armarResultadoVerificacionPiscina($reserva);
    }

    private function armarResultadoVerificacionPiscina(PiscinaReserva $reserva): array
    {
        $campoNombre = $reserva->campo?->nombre;

        return [
            'tipo'               => 'piscina',
            'codigo_seguimiento' => $reserva->codigo_seguimiento,
            'estado_solicitud'   => $reserva->estado->value,
            'titular' => [
                'nombre'   => $reserva->nombre_pagador,
                'ci_nit'   => $reserva->ci_nit_pagador,
                'telefono' => $reserva->telefono_pagador,
            ],
            'campo_id'      => $reserva->campo_id,
            'campo_nombre'  => $campoNombre,
            'fecha_reserva' => $reserva->detalles->first()?->fecha?->toDateString(),
            'franjas' => $reserva->detalles->map(fn ($d) => [
                'id'                    => $d->id,
                'codigo_reserva'        => $reserva->codigo_seguimiento,
                'campo_nombre'          => $campoNombre,
                'fecha'                 => $d->fecha?->toDateString(),
                'hora_inicio'           => $d->hora_inicio?->format('H:i'),
                'hora_fin'              => $d->hora_fin?->format('H:i'),
                'asistencia_marcada_en' => null,
                'perfil'                => $d->perfil,
                'cantidad'              => $d->cantidad,
            ])->values()->all(),
        ];
    }

    private function armarResultadoVerificacion(
        SolicitudReserva $solicitud,
        ?CampoDeportivo $campoReferencia,
        array $reservas,
    ): array {
        return [
            'tipo'               => 'campo',
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
                'id'                    => $r->id,
                'codigo_reserva'        => $r->codigo_reserva,
                'campo_nombre'          => $r->campo?->nombre,
                'fecha'                 => $r->fecha_reserva?->toDateString(),
                'hora_inicio'           => substr((string) $r->hora_inicio, 0, 5),
                'hora_fin'              => substr((string) $r->hora_fin, 0, 5),
                'asistencia_marcada_en' => $r->asistencia_marcada_en?->toIso8601String(),
            ], $reservas),
        ];
    }

    public function turnosPiscina(Funcionario $funcionario, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();

        $query = $this->esAdministrador($funcionario)
            ? CampoDeportivo::query()
            : $funcionario->camposAsignados();

        // Filtro en SQL (no en PHP): el modelo castea modalidad_reserva a enum
        // ModalidadReservaCampo, y `->filter` con === string falla. SQL lo castea
        // automáticamente. Replica el comportamiento del service original.
        $campos = $query
            ->where('estado', 'activo')
            ->where('modalidad_reserva', 'turno_por_perfil')
            ->with(['horariosAtencion', 'tarifas' => fn ($q) => $q->whereNull('vigente_hasta')])
            ->orderBy('nombre')
            ->get();

        return $campos->map(function (CampoDeportivo $campo) use ($fecha) {
            return [
                'campo_id'     => $campo->id,
                'campo_nombre' => $campo->nombre,
                'fecha'        => $fecha->toDateString(),
                'turnos'       => $this->turnosDelCampoPiscina($campo, $fecha),
            ];
        })->values()->all();
    }

    private function turnosDelCampoPiscina(CampoDeportivo $campo, Carbon $fecha): array
    {
        return PiscinaTurno::where('campo_id', $campo->id)
            ->where('fecha', $fecha->toDateString())
            ->where('estado', 'activo')
            ->with('perfiles')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (PiscinaTurno $t) => [
                'turno_id'    => $t->id,
                'hora_inicio' => substr((string) $t->hora_inicio, 0, 5),
                'hora_fin'    => substr((string) $t->hora_fin, 0, 5),
                'cupo_total'  => $t->cupo_total,
                'perfiles' => $t->perfiles->map(fn ($p) => [
                    'perfil'     => $p->perfil,
                    'etiqueta'   => $this->etiquetaPerfilPiscina($p->perfil),
                    'cupo'       => $p->cupo,
                    'reservado'  => $p->reservado,
                    'disponible' => $p->disponible,
                ])->values()->all(),
            ])->values()->all();
    }

    private function etiquetaPerfilPiscina(string $perfil): string
    {
        return match ($perfil) {
            'mayor_12'    => 'Mayores de 12',
            'menor_12'    => 'Menores de 12',
            'institucion' => 'Institución',
            default       => $perfil,
        };
    }

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
            $franjas = $ocupacion['franjas'];

            $totales    = count($franjas);
            $ocupadas   = count(array_filter($franjas, fn ($f) => $f['estado'] === self::FRANJA_OCUPADA));
            $pendientes = count(array_filter($franjas, fn ($f) => $f['estado'] === self::FRANJA_PENDIENTE));
            $libres     = count(array_filter($franjas, fn ($f) => $f['estado'] === self::FRANJA_LIBRE));

            $porcentajeOcupacion = $totales > 0
                ? round(($ocupadas / $totales) * 100, 1)
                : 0.0;

            $ocupadoAhora = $this->estaOcupadoAhora($franjas, $fecha);

            return array_merge($ocupacion, [
                'latitud'  => (float) $campo->latitud,
                'longitud' => (float) $campo->longitud,
                'direccion' => $campo->direccion,
                'estado_operativo'   => $campo->estado,
                'es_campo_operativo' => $this->esCampoOperativo($campo),
                'vinculacion_sireb' => [
                    'vinculado'             => $campo->servicio_sireb_id !== null,
                    'servicio_sireb_id'     => $campo->servicio_sireb_id,
                    'servicio_sireb_codigo' => $campo->servicio_sireb_codigo,
                ],
                'resumen_ocupacion' => [
                    'franjas_totales'       => $totales,
                    'franjas_ocupadas'      => $ocupadas,
                    'franjas_pendientes'    => $pendientes,
                    'franjas_libres'        => $libres,
                    'porcentaje_ocupacion'  => $porcentajeOcupacion,
                    'ocupado_ahora'         => $ocupadoAhora,
                ],
            ]);
        })->values()->all();
    }

    private function esCampoOperativo(CampoDeportivo $campo): bool
    {
        if ($campo->estado !== 'activo') {
            return false;
        }
        if ($campo->tarifas->isEmpty()) {
            return false;
        }
        if ($this->esModalidadPiscina($campo->modalidad_reserva)) {
            return PiscinaTurno::where('campo_id', $campo->id)
                ->where('estado', 'activo')
                ->exists();
        }
        return $campo->horariosAtencion->isNotEmpty();
    }

    private function estaOcupadoAhora(array $franjas, Carbon $fecha): bool
    {
        if (! now()->isSameDay($fecha)) {
            return false;
        }

        $ahoraSeg = (int) now()->format('H') * 3600
            + (int) now()->format('i') * 60
            + (int) now()->format('s');

        foreach ($franjas as $franja) {
            if ($franja['estado'] !== self::FRANJA_OCUPADA) {
                continue;
            }

            $inicio = Carbon::createFromFormat('H:i:s', $franja['hora_inicio']);
            $fin    = Carbon::createFromFormat('H:i:s', $franja['hora_fin']);

            $iniSeg = (int) $inicio->format('H') * 3600 + (int) $inicio->format('i') * 60 + (int) $inicio->format('s');
            $finSeg = (int) $fin->format('H') * 3600    + (int) $fin->format('i') * 60    + (int) $fin->format('s');

            if ($ahoraSeg >= $iniSeg && $ahoraSeg < $finSeg) {
                return true;
            }
        }

        return false;
    }

    private function obtenerCamposAsignados(Funcionario $funcionario, bool $incluirPiscina = true): Collection
    {
        $query = $this->esAdministrador($funcionario)
            ? CampoDeportivo::query()
            : $funcionario->camposAsignados();

        $query->where('estado', 'activo')
            ->with(['horariosAtencion', 'tarifas' => fn ($q) => $q->whereNull('vigente_hasta')]);

        if (! $incluirPiscina) {
            $query->where(function ($q) {
                $q->whereNull('modalidad_reserva')
                    ->orWhere('modalidad_reserva', '!=', 'turno_por_perfil');
            });
        }

        return $query->get();
    }

    private function ocupacionDelCampo(CampoDeportivo $campo, Carbon $fecha): array
    {
        $horario = $campo->horariosAtencion()
            ->where('dia_semana', $fecha->dayOfWeekIso)
            ->first();

        if (! $horario) {
            return [
                'campo_id'     => $campo->id,
                'campo_nombre' => $campo->nombre,
                'abierto'      => false,
                'fecha'        => $fecha->toDateString(),
                'franjas'      => [],
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
                'hora_inicio'           => $franja['hora_inicio'],
                'hora_fin'              => $franja['hora_fin'],
                'estado'                => $estado['estado'],
                'codigo_reserva'        => $estado['codigo_reserva'],
                'asistencia_marcada_en' => $estado['asistencia_marcada_en'],
            ];
        })->all();

        return [
            'campo_id'     => $campo->id,
            'campo_nombre' => $campo->nombre,
            'abierto'      => true,
            'fecha'        => $fecha->toDateString(),
            'franjas'      => $franjas,
        ];
    }

    private function generarFranjas(string $horaApertura, string $horaCierre): Collection
    {
        $franjas = collect();
        $inicio  = Carbon::createFromFormat('H:i:s', $horaApertura);
        $fin     = Carbon::createFromFormat('H:i:s', $horaCierre);

        while ($inicio->copy()->addMinutes(self::TAMANO_BLOQUE_MINUTOS)->lte($fin)) {
            $finFranja = $inicio->copy()->addMinutes(self::TAMANO_BLOQUE_MINUTOS);
            $franjas->push([
                'hora_inicio' => $inicio->format('H:i:s'),
                'hora_fin'    => $finFranja->format('H:i:s'),
            ]);
            $inicio = $finFranja;
        }

        return $franjas;
    }

    private function calcularEstadoFranja(array $franja, Collection $solicitudes): array
    {
        $franjaInicio = Carbon::createFromFormat('H:i:s', $franja['hora_inicio']);
        $franjaFin    = Carbon::createFromFormat('H:i:s', $franja['hora_fin']);

        $confirmada          = false;
        $pendiente           = false;
        $codigoReserva       = null;
        $asistenciaMarcadaEn = null;

        foreach ($solicitudes as $s) {
            $sInicio = Carbon::createFromFormat('H:i:s', $s->hora_inicio);
            $sFin    = Carbon::createFromFormat('H:i:s', $s->hora_fin);

            if ($franjaInicio->lt($sFin) && $franjaFin->gt($sInicio)) {
                if ($s->estado_solicitud === EstadoSolicitudReserva::Confirmada->value) {
                    $confirmada = true;
                    if ($s->reserva) {
                        $codigoReserva       = $s->reserva->codigo_reserva;
                        $asistenciaMarcadaEn = $s->reserva->asistencia_marcada_en?->toIso8601String();
                    }
                } elseif ($s->estado_solicitud === EstadoSolicitudReserva::Pendiente->value) {
                    $pendiente = true;
                }
            }
        }

        if ($confirmada) {
            return [
                'estado'                => self::FRANJA_OCUPADA,
                'codigo_reserva'        => $codigoReserva,
                'asistencia_marcada_en' => $asistenciaMarcadaEn,
            ];
        }

        if ($pendiente) {
            return [
                'estado'                => self::FRANJA_PENDIENTE,
                'codigo_reserva'        => null,
                'asistencia_marcada_en' => null,
            ];
        }

        return [
            'estado'                => self::FRANJA_LIBRE,
            'codigo_reserva'        => null,
            'asistencia_marcada_en' => null,
        ];
    }

    private function esAdministrador(Funcionario $funcionario): bool
    {
        if ($funcionario->tienePermiso('*')) {
            return true;
        }

        return in_array($funcionario->rol?->nombre, self::ROLES_ADMIN, true);
    }

    private function campoAsignado(Funcionario $funcionario, string $campoId): bool
    {
        return $funcionario->camposAsignados()
            ->where('campos_deportivos.id', $campoId)
            ->exists();
    }

    /**
     * Tolerante a enum backed (ModalidadReservaCampo) o string.
     */
    private function esModalidadPiscina(mixed $modalidad): bool
    {
        if ($modalidad instanceof \BackedEnum) {
            return $modalidad->value === 'turno_por_perfil';
        }
        return $modalidad === 'turno_por_perfil';
    }
}
