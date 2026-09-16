<?php

namespace App\Services;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudCobroDTO;
use App\DTOs\SolicitudCreadaDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Exceptions\RecaudacionesApiException;
use App\Exceptions\ServicioDeCobroNoDisponibleException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Jobs\ExpirarSolicitudJob;
use App\Jobs\PollingSolicitudJob;
use App\Models\CampoDeportivo;
use App\Models\ParametroSistema;
use App\Models\SolicitudReserva;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creación de solicitudes de reserva multi-franja (HU-D1, HU-D2, HU-D3, HU-D8).
 *
 * Dos capas de protección anti-doble-reserva:
 *   1. Verificación rápida (aplicativa) con la grilla del Módulo 3.
 *   2. Escritura protegida: la restricción EXCLUDE decide en concurrencia.
 *
 * Recién DESPUÉS de que la transacción tiene éxito se llama al Core de
 * Recaudaciones — nunca antes. Si el Core no responde (HU-D8), la
 * solicitud queda 'rechazada' de inmediato y la franja se libera al acto.
 */
class SolicitudReservaService
{
    public const VENTANA_DIAS = 60;

    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private DisponibilidadService $disponibilidad,
        private RecaudacionesApiClientInterface $recaudaciones,
        private AuditoriaService $auditoria,
    ) {
    }

    public function crear(SolicitudReservaDTO $datos): SolicitudCreadaDTO
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
            if ($e->getCode() === '23P01') {
                // La restricción EXCLUDE decidió. Re-corremos la verificación
                // rápida contra datos ya commiteados para identificar CUÁL
                // franja perdió y devolvérsela estructurada a la app.
                try {
                    $this->verificarFranjas($datos->franjas);
                } catch (FranjaNoDisponibleException $especifica) {
                    throw $especifica;
                }

                throw new FranjaNoDisponibleException(
                    'Una de las franjas seleccionadas ya no está disponible.',
                );
            }
            throw $e;
        }

        // ── Recién aquí, con la transacción exitosa, se llama al Core ──
        try {
            $respuestaCore = $this->recaudaciones->solicitarCobro(new SolicitudCobroDTO(
                referenciaExterna: $solicitud->codigo_seguimiento,
                monto: (float) $solicitud->monto_total,
                nombrePagador: $solicitud->nombre_pagador,
                telefonoPagador: $solicitud->telefono_pagador,
                ciNitPagador: $solicitud->ci_nit_pagador,
                descripcion: $this->describirSolicitud($solicitud),
            ));
        } catch (RecaudacionesApiException $e) {
            // HU-D8: resolución inmediata. No se despacha job de expiración:
            // el estado queda resuelto de forma síncrona y la franja se
            // libera en el acto (el EXCLUDE deja de verla como activa).
            $solicitud->update(['estado' => EstadoSolicitudReserva::Rechazada]);

            $this->auditoria->registrar(
                'solicitudes_reserva',
                $solicitud->id,
                'fallo_conexion_core',
                null,
                null,
                ['error' => $e->getMessage()],
            );

            throw new ServicioDeCobroNoDisponibleException(
                'El sistema de cobro no está disponible en este momento. Intenta nuevamente en unos minutos.',
            );
        }

        $solicitud->update([
            'referencia_recaudaciones' => $respuestaCore->referenciaRecaudaciones,
            'datos_cobro_pendiente' => [
                'qr_string' => $respuestaCore->qrString,
                'qr_image_base64' => $respuestaCore->qrImageBase64,
                'checkout_url' => $respuestaCore->checkoutUrl,
            ],
        ]);

        // ── NUEVO: Despachar jobs de expiración y polling ──
        ExpirarSolicitudJob::dispatch($solicitud->id)->delay($solicitud->expira_en);

        $intervaloPolling = (int) ParametroSistema::where('clave', 'polling_intervalo_segundos')->value('valor');
        PollingSolicitudJob::dispatch($solicitud->id)->delay(now()->addSeconds($intervaloPolling ?: 20));

        return new SolicitudCreadaDTO($solicitud, $respuestaCore);
    }

    /** Descripción legible del cobro para el Core / comprobante. */
    private function describirSolicitud(SolicitudReserva $solicitud): string
    {
        $detalle = $solicitud->detalles()->with('campo')->first();
        $campoNombre = $detalle?->campo?->nombre ?? 'Campo';
        $fecha = $detalle?->fecha_reserva?->format('d/m') ?? '';
        $total = $solicitud->detalles()->count();

        return "Reserva {$campoNombre} - {$fecha}"
            . ($total > 1 ? " (+".($total - 1).' franjas)' : '');
    }

    /**
     * Capa 1: valida estado del campo, tarifa vigente, ventana de fechas,
     * horario de atención y disponibilidad de cada franja contra la grilla.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @return array<string, array{tarifa: float}>
     */
    private function verificarFranjas(array $franjas): array
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

            $tarifa = $campo->tarifas()->whereNull('vigente_hasta')->first();
            if (! $tarifa) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no tiene tarifa vigente definida.",
                ]);
            }

            $campos[$campo->id] = ['tarifa' => (float) $tarifa->precio_por_hora];

            $fecha = Carbon::parse($primera->fecha)->startOfDay();
            $this->validarVentana($fecha);

            $this->validarSolapeInterno($grupo->all());

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
                [
                    'campo_id' => $franja->campoId,
                    'fecha' => $franja->fecha,
                    'hora_inicio' => $franja->horaInicio,
                    'hora_fin' => $franja->horaFin,
                ],
            );
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

    private static function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }
}
