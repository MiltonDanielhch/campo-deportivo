<?php

namespace App\Services\SolicitudesReserva;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Models\ArancelVariante;
use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use App\Models\CampoDeportivo;
use App\Models\ParametroSistema;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Services\AsociacionVigenteResolver;
use App\Services\DisponibilidadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validación de franjas, anti-hoarding, convenio/alumnos y helpers de
 * expiración/código/nombre para solicitudes de reserva.
 *
 * Extraído de SolicitudReservaService (PR #59). No toca persistencia ni
 * cobro: solo valida y prepara datos antes de que el orquestador arme
 * la transacción.
 */
class SolicitudReservaValidationService
{
    public const VENTANA_DIAS = 60;

    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const TIMEZONE_BOLIVIA = 'America/La_Paz';

    private const MARGEN_ANTICIPACION_MINUTOS = 0;

    public function __construct(
        private DisponibilidadService $disponibilidad,
        private AsociacionVigenteResolver $asociacionResolver,
    ) {}

    /**
     * Capa 1: valida estado del campo, tarifa vigente, ventana de fechas,
     * horario de atención, disponibilidad de cada franja contra la grilla
     * y que la franja no haya comenzado aún.
     *
     * [FIX B77-B] $saltarGatesTiempo=true (regularización) omite validarVentana
     * y validarFranjaFutura, y hace que verificarFranjaEnGrilla tolere bloques
     * 'pasada'. La integridad de disponibilidad (campo activo, tarifas, unicidad,
     * solape interno, horario, EXCLUDE) NO se relaja.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @return array<string, array{campo: CampoDeportivo, tarifas: \Illuminate\Support\Collection<string, TarifaCampo>, variantes: \Illuminate\Support\Collection<string, ArancelVariante>, inicio_noche: int}>
     */
    public function verificarFranjas(array $franjas, bool $saltarGatesTiempo = false): array
    {
        $campos = [];

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

            $tarifasActivas = $campo->tarifas()
                ->paraGrillaHoraria()
                ->get();

            if ($tarifasActivas->isEmpty()) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no tiene tarifa SIREB vigente espejada. Ejecutá la sincronización desde SIREB.",
                ]);
            }

            $variantes = ArancelVariante::query()
                ->where('campo_id', $campo->id)
                ->where('activo', true)
                ->orderBy('orden')
                ->get()
                ->keyBy('id');

            $porServicioYClasificacion = $tarifasActivas->groupBy(
                fn (TarifaCampo $t) => ($t->servicio_sireb_id ?? 'legacy').'|'.$t->clasificacion
            );

            foreach ($porServicioYClasificacion as $clave => $coleccion) {
                if ($coleccion->count() > 1) {
                    [$servicio, $clasificacion] = array_pad(explode('|', (string) $clave, 2), 2, '');

                    throw ValidationException::withMessages([
                        'franjas' => "El campo {$campo->nombre} tiene más de una tarifa espejada activa para el servicio '{$servicio}' y clasificación '{$clasificacion}'. Revisá el tarifario en SIREB.",
                    ]);
                }
            }

            $campos[$campo->id] = [
                'campo' => $campo,
                'tarifas' => $tarifasActivas,
                'variantes' => $variantes,
                'inicio_noche' => SolicitudReservaPricingService::aSegundos($campo->hora_inicio_noche ?? '18:00:00'),
            ];

            $fecha = Carbon::parse($primera->fecha, self::TIMEZONE_BOLIVIA)->startOfDay();

            if (! $saltarGatesTiempo) {
                $this->validarVentana($fecha);
            }

            $this->validarSolapeInterno($grupo->all());

            $campo->load(['tarifas' => fn ($q) => $q->paraGrillaHoraria()]);

            $grilla = $this->disponibilidad->calcularGrilla($campo, $fecha);

            if (! $grilla['abierto']) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no abre el {$primera->fecha}.",
                ]);
            }

            foreach ($grupo as $franja) {
                if (! $saltarGatesTiempo) {
                    $this->validarFranjaFutura($franja);
                }

                $this->verificarFranjaEnGrilla($franja, $grilla, $saltarGatesTiempo);
            }
        }

        return $campos;
    }

    private function verificarFranjaEnGrilla(FranjaSolicitadaDTO $franja, array $grilla, bool $saltarGatesTiempo = false): void
    {
        $fInicio = SolicitudReservaPricingService::aSegundos($franja->horaInicio);
        $fFin = SolicitudReservaPricingService::aSegundos($franja->horaFin);

        if ($fFin <= $fInicio) {
            throw ValidationException::withMessages([
                'franjas' => 'La hora de fin debe ser posterior a la hora de inicio.',
            ]);
        }

        $solapados = collect($grilla['bloques'])->filter(
            fn (array $b) => SolicitudReservaPricingService::aSegundos($b['hora_inicio']) < $fFin
                && SolicitudReservaPricingService::aSegundos($b['hora_fin']) > $fInicio,
        );

        if ($solapados->isEmpty()) {
            throw ValidationException::withMessages([
                'franjas' => "La franja {$franja->horaInicio}-{$franja->horaFin} está fuera del horario de atención.",
            ]);
        }

        $tolerados = $saltarGatesTiempo ? ['libre', 'pasada'] : ['libre'];

        if ($solapados->contains(fn (array $b) => ! in_array($b['estado'], $tolerados, true))) {
            throw new FranjaNoDisponibleException(
                "La franja {$franja->horaInicio}-{$franja->horaFin} del {$franja->fecha} ya no está disponible.",
                [
                    'campo_id' => $franja->campoId,
                    'fecha' => $franja->fecha,
                    'hora_inicio' => $franja->horaInicio,
                    'hora_fin' => $franja->horaFin,
                ],
            );
        }
    }

    private function validarFranjaFutura(FranjaSolicitadaDTO $franja): void
    {
        $inicioFranja = Carbon::parse(
            "{$franja->fecha} {$franja->horaInicio}",
            self::TIMEZONE_BOLIVIA,
        );

        $umbral = $this->ahoraBolivia()->addMinutes(self::MARGEN_ANTICIPACION_MINUTOS);

        if ($inicioFranja->lessThanOrEqualTo($umbral)) {
            throw ValidationException::withMessages([
                'franjas' => "La franja {$franja->horaInicio}-{$franja->horaFin} del {$franja->fecha} ya comenzó. Elegí un horario futuro.",
            ]);
        }
    }

    /**
     * @param  FranjaSolicitadaDTO[]  $franjas
     */
    private function validarSolapeInterno(array $franjas): void
    {
        $total = count($franjas);

        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $aIni = SolicitudReservaPricingService::aSegundos($franjas[$i]->horaInicio);
                $aFin = SolicitudReservaPricingService::aSegundos($franjas[$i]->horaFin);
                $bIni = SolicitudReservaPricingService::aSegundos($franjas[$j]->horaInicio);
                $bFin = SolicitudReservaPricingService::aSegundos($franjas[$j]->horaFin);

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
        $hoy = $this->ahoraBolivia()->startOfDay();

        if ($fecha->lt($hoy) || $fecha->gt($hoy->copy()->addDays(self::VENTANA_DIAS))) {
            throw ValidationException::withMessages([
                'franjas' => 'La fecha debe estar entre hoy y 60 días en el futuro.',
            ]);
        }
    }

    private function ahoraBolivia(): Carbon
    {
        return Carbon::now(self::TIMEZONE_BOLIVIA);
    }

    /**
     * Gate anti-hoarding: 3 chequeos que corren ANTES de crear el hold.
     *
     *  M1 dinámico: techo de franjas por solicitud (el validator ya corta en 6).
     *  M2: techo de solicitudes pendientes activas por IP.
     *  M3: IP en cooldown por expiraciones repetidas.
     *
     * @throws ValidationException
     */
    public function validarAntiHoarding(SolicitudReservaDTO $datos, ?Asociacion $asociacionConocida = null): void
    {
        if (filled($datos->ciNitPagador)) {
            $asociacion = $asociacionConocida ?? $this->resolverAsociacionVigente($datos->ciNitPagador);
            $maxPorCi = $asociacion
                ? (int) (ParametroSistema::where('clave', 'max_pendientes_por_asociacion')->value('valor') ?: 0)
                : (int) (ParametroSistema::where('clave', 'max_pendientes_por_ci')->value('valor') ?: 3);

            if ($maxPorCi > 0) {
                $pendientesCi = SolicitudReserva::where('ci_nit_pagador', $datos->ciNitPagador)
                    ->where('estado', EstadoSolicitudReserva::Pendiente)
                    ->where('expira_en', '>', now())
                    ->count();

                if ($pendientesCi >= $maxPorCi) {
                    throw ValidationException::withMessages([
                        'franjas' => $asociacion
                            ? "La asociación ya tiene {$pendientesCi} reservas pendientes. Liquidá alguna antes de generar más."
                            : "Tenés {$pendientesCi} reservas pendientes por pagar. Confirmá o esperá a que expiren antes de crear una nueva.",
                    ]);
                }
            }
        }

        if (! $datos->ipCobrador) {
            return;
        }

        $maxFranjas = (int) ParametroSistema::where('clave', 'max_franjas_por_solicitud')->value('valor') ?: 6;
        if (count($datos->franjas) > $maxFranjas) {
            throw ValidationException::withMessages([
                'franjas' => "No podés reservar más de {$maxFranjas} franjas en una sola solicitud.",
            ]);
        }

        $cooldownKey = $this->cooldownKey($datos->ipCobrador);
        if (Cache::has($cooldownKey)) {
            $minutosRestantes = (int) Cache::get($cooldownKey, 0);
            throw ValidationException::withMessages([
                'franjas' => "Esta IP fue bloqueada temporalmente por reservas sin pagar. Intentá nuevamente en {$minutosRestantes} minutos.",
            ]);
        }

        $maxPendientes = (int) ParametroSistema::where('clave', 'max_pendientes_por_ip')->value('valor') ?: 3;
        $pendientesActivas = SolicitudReserva::where('ip_cobrador', $datos->ipCobrador)
            ->where('estado', EstadoSolicitudReserva::Pendiente)
            ->where('expira_en', '>', now())
            ->count();

        if ($pendientesActivas >= $maxPendientes) {
            throw ValidationException::withMessages([
                'franjas' => "Tenés {$pendientesActivas} reservas pendientes por pagar. Confirmá o esperá a que expiren antes de crear una nueva.",
            ]);
        }
    }

    private function cooldownKey(string $ip): string
    {
        return 'hoard:cooldown:'.md5($ip);
    }

    /**
     * [convenio-guard] Ninguna franja resuelta puede usar una variante con
     * tipo_asociacion filled salvo que el pagador sea asociación VIGENTE del
     * MISMO tipo.
     */
    public function validarVariantesConvenio(array $franjasResueltas, ?Asociacion $asociacion): void
    {
        foreach ($franjasResueltas as $resuelta) {
            $vid = $resuelta['variante_id'] ?? null;
            if (blank($vid)) {
                continue;
            }
            $variante = ArancelVariante::find($vid);
            if ($variante && filled($variante->tipo_asociacion)) {
                $tipoAsoc = $asociacion?->tipo?->value;
                if ($tipoAsoc !== $variante->tipo_asociacion) {
                    throw ValidationException::withMessages([
                        'franjas' => "La variante '{$variante->nombre}' es de convenio ({$variante->tipo_asociacion}) y el CI/NIT informado no corresponde a una asociación vigente de ese tipo.",
                    ]);
                }
            }
        }
    }

    /**
     * [Fase 3] Si alguna franja trae alumnos, la asociacion del pagador debe
     * existir (vigente) y CADA alumno debe pertenecer a ella.
     */
    public function validarAlumnosPertenencia(array $franjasResueltas, ?Asociacion $asociacion): void
    {
        $ids = [];
        foreach ($franjasResueltas as $resuelta) {
            foreach ($resuelta['franja']->alumnosIds as $id) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        if ($asociacion === null) {
            throw ValidationException::withMessages([
                'franjas' => 'Solo una asociación vigente puede asignar alumnos a una franja.',
            ]);
        }

        $unicos = array_values(array_unique($ids));
        $pertenecen = AsociacionAlumno::whereIn('id', $unicos)
            ->where('asociacion_id', $asociacion->id)
            ->pluck('id')
            ->map(fn ($v) => (string) $v)
            ->all();

        if (array_diff($unicos, $pertenecen) !== []) {
            throw ValidationException::withMessages([
                'franjas' => 'Uno o más alumnos asignados no pertenecen a la asociación del pagador.',
            ]);
        }
    }

    /**
     * [convenio] Lookup por el CI/NIT del responsable que reserva.
     */
    public function resolverAsociacionVigente(?string $ciNit): ?Asociacion
    {
        return $this->asociacionResolver->porCi($ciNit);
    }

    private function minutosExpiracion(): int
    {
        $valor = ParametroSistema::query()
            ->where('clave', 'solicitud_reserva_expiracion_minutos')
            ->value('valor');

        return (int) ($valor ?? 15);
    }

    /**
     * [convenio] Plazo de hold: público = minutos (15 default); asociación
     * vigente + ventanilla/convenioOnline = DÍAS.
     */
    public function calcularExpiracionMinutos(?Asociacion $asociacion, bool $esAdmin, bool $convenioOnline = false): int
    {
        if ($asociacion === null || (! $esAdmin && ! $convenioOnline)) {
            return $this->minutosExpiracion();
        }

        $dias = (int) (ParametroSistema::where('clave', 'convenio_expiracion_dias')->value('valor') ?: 3);

        return max(1, $dias) * 24 * 60;
    }

    public function generarCodigoSeguimiento(): string
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

    /**
     * [FIX B65 cancha] Normaliza nombre del pagador: trim + title case.
     */
    public function normalizarPagador(?string $nombre): ?string
    {
        if ($nombre === null) {
            return null;
        }
        $trim = trim($nombre);
        if ($trim === '') {
            return null;
        }
        return Str::title(Str::lower($trim));
    }
}
