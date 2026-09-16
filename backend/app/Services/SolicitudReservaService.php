<?php

namespace App\Services;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Models\CampoDeportivo;
use App\Models\ParametroSistema;
use App\Models\SolicitudReserva;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creación de solicitudes de reserva multi-franja (HU-D1, HU-D2).
 *
 * Dos capas de protección:
 *   1. Verificación rápida (aplicativa) con la grilla del Módulo 3:
 *      da el error amigable en el caso común.
 *   2. Escritura protegida: el INSERT vive en una transacción y la
 *      restricción EXCLUDE (no_solape_horario) decide quién gana si
 *      dos ciudadanos llegan a la misma franja al mismo tiempo.
 */
class SolicitudReservaService
{
    /** Misma ventana de reserva que valida el Módulo 3. */
    public const VENTANA_DIAS = 60;

    /** Alfabeto sin caracteres ambiguos (0/O, 1/I) para lectura por teléfono. */
    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private DisponibilidadService $disponibilidad)
    {
    }

    public function crear(SolicitudReservaDTO $datos): SolicitudReserva
    {
        // ── Capa 1: verificación rápida (aplicativa) ──
        $campos = $this->verificarFranjas($datos->franjas);

        // ── Capa 2: escritura protegida (la garantía real es el EXCLUDE) ──
        $expiracionMinutos = $this->minutosExpiracion();

        try {
            $solicitud = DB::transaction(function () use ($datos, $campos, $expiracionMinutos) {
                $solicitudNueva = SolicitudReserva::create([
                    'codigo_seguimiento' => $this->generarCodigoSeguimiento(),
                    'monto_total' => $this->calcularMontoTotal($datos->franjas, $campos),
                    'nombre_pagador' => $datos->nombrePagador,
                    'telefono_pagador' => $datos->telefonoPagador,
                    'ci_nit_pagador' => $datos->ciNitPagador,
                    'estado' => EstadoSolicitudReserva::Pendiente,
                    'expira_en' => now()->addMinutes($expiracionMinutos),
                ]);

                foreach ($datos->franjas as $franja) {
                    $solicitudNueva->detalles()->create([
                        'campo_id' => $franja->campoId,
                        'fecha_reserva' => $franja->fecha,
                        'hora_inicio' => $franja->horaInicio,
                        'hora_fin' => $franja->horaFin,
                        'tarifa_aplicada' => $campos[$franja->campoId]['tarifa'],
                    ]);
                }

                return $solicitudNueva;
            });
        } catch (QueryException $e) {
            // 23P01 = exclusion_violation: el EXCLUDE decidió que esta
            // franja ya tiene dueño. Caso de concurrencia real.
            if ($e->getCode() === '23P01') {
                throw new FranjaNoDisponibleException(
                    'Una de las franjas seleccionadas ya no está disponible.',
                );
            }
            throw $e;
        }

        // TODO (Fase 4.2): recién aquí, con la transacción exitosa,
        // se llama al Core de Recaudaciones. Nunca antes.

        return $solicitud;
    }

    /**
     * Capa 1: valida estado del campo, tarifa vigente, ventana de fechas,
     * horario de atención y disponibilidad de cada franja contra la grilla.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @return array<string, array{tarifa: float}> tarifas congeladas por campo
     */
    private function verificarFranjas(array $franjas): array
    {
        $campos = [];

        // Agrupa por campo+fecha para calcular la grilla una sola vez por grupo
        $grupos = collect($franjas)->groupBy(
            fn (FranjaSolicitadaDTO $f) => $f->campoId.'|'.$f->fecha,
        );

        foreach ($grupos as $grupo) {
            $primera = $grupo->first();

            $campo = CampoDeportivo::find($primera->campoId);
            if (! $campo || $campo->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'franjas' => 'El campo seleccionado no está disponible para reserva.',
                ]);
            }

            $tarifa = $campo->tarifas()->whereNull('vigente_hasta')->first();
            if (! $tarifa) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no tiene tarifa vigente definida.",
                ]);
            }

            $campos[$campo->id] = ['tarifa' => (float) $tarifa->precio_por_hora];

            $fecha = Carbon::parse($primera->fecha)->startOfDay();
            $this->validarVentana($fecha);

            // Ninguna franja del mismo pedido puede solaparse con otra
            $this->validarSolapeInterno($grupo->all());

            // La grilla necesita la tarifa vigente cargada (no cualquier tarifa)
            $campo->load(['tarifas' => fn ($q) => $q->whereNull('vigente_hasta')]);
            $grilla = $this->disponibilidad->calcularGrilla($campo, $fecha);

            if (! $grilla['abierto']) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no abre el {$primera->fecha}.",
                ]);
            }

            foreach ($grupo as $franja) {
                $this->verificarFranjaEnGrilla($franja, $grilla);
            }
        }

        return $campos;
    }

    /**
     * Cruza una franja contra los bloques de la grilla:
     * - Sin bloques solapados → fuera del horario de atención (422).
     * - Algún bloque solapado no libre → ya tiene dueño (409).
     */
    private function verificarFranjaEnGrilla(FranjaSolicitadaDTO $franja, array $grilla): void
    {
        $fInicio = self::aSegundos($franja->horaInicio);
        $fFin = self::aSegundos($franja->horaFin);

        if ($fFin <= $fInicio) {
            throw ValidationException::withMessages([
                'franjas' => 'La hora de fin debe ser posterior a la hora de inicio.',
            ]);
        }

        $solapados = collect($grilla['bloques'])->filter(
            fn (array $b) => self::aSegundos($b['hora_inicio']) < $fFin
                && self::aSegundos($b['hora_fin']) > $fInicio,
        );

        if ($solapados->isEmpty()) {
            throw ValidationException::withMessages([
                'franjas' => "La franja {$franja->horaInicio}-{$franja->horaFin} está fuera del horario de atención.",
            ]);
        }

        if ($solapados->contains(fn (array $b) => $b['estado'] !== 'libre')) {
            throw new FranjaNoDisponibleException(
                "La franja {$franja->horaInicio}-{$franja->horaFin} del {$franja->fecha} ya no está disponible.",
            );
        }
    }

    /**
     * Dos franjas del MISMO pedido no pueden pisarse entre sí.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     */
    private function validarSolapeInterno(array $franjas): void
    {
        $total = count($franjas);

        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $aIni = self::aSegundos($franjas[$i]->horaInicio);
                $aFin = self::aSegundos($franjas[$i]->horaFin);
                $bIni = self::aSegundos($franjas[$j]->horaInicio);
                $bFin = self::aSegundos($franjas[$j]->horaFin);

                if ($aIni < $bFin && $aFin > $bIni) {
                    throw ValidationException::withMessages([
                        'franjas' => 'Hay franjas superpuestas dentro de la misma solicitud.',
                    ]);
                }
            }
        }
    }

    private function validarVentana(Carbon $fecha): void
    {
        $hoy = Carbon::today();

        if ($fecha->lt($hoy) || $fecha->gt($hoy->copy()->addDays(self::VENTANA_DIAS))) {
            throw ValidationException::withMessages([
                'franjas' => 'La fecha debe estar entre hoy y 60 días en el futuro.',
            ]);
        }
    }

    /**
     * La franja es la unidad de cobro: una tarifa vigente por franja,
     * congelada en tarifa_aplicada al momento de crear la solicitud.
     */
    private function calcularMontoTotal(array $franjas, array $campos): float
    {
        return (float) collect($franjas)->sum(
            fn (FranjaSolicitadaDTO $f) => $campos[$f->campoId]['tarifa'],
        );
    }

    private function minutosExpiracion(): int
    {
        $valor = ParametroSistema::query()
            ->where('clave', 'solicitud_reserva_expiracion_minutos')
            ->value('valor');

        return (int) ($valor ?? 15);
    }

    /**
     * RES-YYYYMMDD-XXXXXX — legible por teléfono para soporte,
     * 20 caracteres (límite de columna: 40).
     */
    private function generarCodigoSeguimiento(): string
    {
        do {
            $aleatorio = '';
            for ($i = 0; $i < 6; $i++) {
                $aleatorio .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
            $codigo = 'RES-'.now()->format('Ymd').'-'.$aleatorio;
        } while (SolicitudReserva::where('codigo_seguimiento', $codigo)->exists());

        return $codigo;
    }

    /** Convierte 'HH:MM' o 'HH:MM:SS' a segundos desde medianoche. */
    private static function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }
}
