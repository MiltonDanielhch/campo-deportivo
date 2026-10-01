<?php

namespace App\Services;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudCreadaDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Jobs\ExpirarSolicitudJob;
use App\Jobs\PollingSolicitudJob;
use App\Jobs\ReintentarSolicitudJob;
use App\Models\CampoDeportivo;
use App\Models\ParametroSistema;
use App\Models\SolicitudReserva;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Creación de solicitudes de reserva multi-franja (HU-D1, HU-D2, HU-D3, HU-D8).
 *
 * Dos capas de protección anti-doble-reserva:
 *   1. Verificación rápida (aplicativa) con la grilla del Módulo 3.
 *   2. Escritura protegida: la restricción EXCLUDE decide en concurrencia.
 *
 * Recién DESPUÉS de que la transacción tiene éxito se llama a SIREB.
 * Si SIREB no responde (HU-D8), la solicitud queda 'pendiente' y se
 * despacha un ReintentarSolicitudJob (5 reintentos × 60s). Solo si los
 * 5 fallan, la solicitud pasa a 'rechazada' con motivo 'error_cobro_inicial'
 * y la franja se libera.
 *
 * El monto de la solicitud se calcula localmente como ESTIMACIÓN (según
 * tipo de tarifa diurna/nocturna de la franja). Al crear la liquidación,
 * SIREB es la fuente de verdad: monto_total se sobrescribe con el monto
 * devuelto por el gateway.
 */
class SolicitudReservaService
{
    public const VENTANA_DIAS = 60;

    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private DisponibilidadService $disponibilidad,
        private RecaudacionesApiClientInterface $recaudaciones,
        private AuditoriaService $auditoria,
        private CatalogoSirebService $catalogo,
    ) {
    }

    public function crear(SolicitudReservaDTO $datos): SolicitudCreadaDTO
    {
        // ── Capa 1: verificación rápida (aplicativa) ──
        $campos = $this->verificarFranjas($datos->franjas);

        // ── Capa 2: escritura protegida (EXCLUDE) ──
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
                        'tarifa_aplicada' => $this->precioDeFranja($franja, $campos[$franja->campoId]),
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

        // ── Capa 3: crear liquidación en SIREB ──
        try {
            $this->crearLiquidacionEnSireb($solicitud);
        } catch (\Throwable $e) {
            // En vez de rechazar de inmediato, despachamos job de reintento.
            // La solicitud queda pendiente y libera la franja solo si todos
            // los reintentos fallan (lo decide el job).
            Log::channel('sireb')->warning(
                'Creación de liquidación falló, despachando reintento',
                [
                    'solicitud_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ]
            );

            ReintentarSolicitudJob::dispatch($solicitud->id);
        }

        // ── Despachar jobs de expiración y polling ──
        ExpirarSolicitudJob::dispatch($solicitud->id)->delay($solicitud->expira_en);

        $intervaloPolling = (int) ParametroSistema::where(
            'clave',
            'polling_intervalo_segundos'
        )->value('valor');
        PollingSolicitudJob::dispatch($solicitud->id)
            ->delay(now()->addSeconds($intervaloPolling ?: 20));

        // ── Devolver respuesta al frontend ──
        return $this->construirRespuesta($solicitud);
    }

    /**
     * Crea la liquidación en SIREB (secuencia completa: cliente + items + POST).
     *
     * @throws \Throwable si algo falla (lo captura el caller para reintentar)
     */
    private function crearLiquidacionEnSireb(SolicitudReserva $solicitud): void
    {
        // 1. Buscar o registrar cliente en SIREB
        $clienteSireb = $this->recaudaciones->buscarCliente($solicitud->ci_nit_pagador);
        if (! $clienteSireb) {
            $clienteSireb = $this->recaudaciones->registrarCliente([
                'ci_nit' => $solicitud->ci_nit_pagador,
                'nombre_completo' => $solicitud->nombre_pagador,
                'telefono' => $solicitud->telefono_pagador,
            ]);
        }

        // 2. Construir items (uno por franja con tarifa_id resuelto)
        $items = [];
        foreach ($solicitud->detalles as $detalle) {
            $campo = $detalle->campo;
            $tarifaId = $this->catalogo->resolverTarifaId($campo, $detalle->hora_inicio);
            $items[] = ['tarifa_id' => $tarifaId, 'cantidad' => 1];
        }

        // 3. Crear liquidación con idempotencia
        $idempotencyKey = "sedede:reserva:{$solicitud->id}";
        $liquidacion = $this->recaudaciones->crearLiquidacion(
            items: $items,
            clienteId: $clienteSireb['id'],
            idempotencyKey: $idempotencyKey,
            referenciaExterna: $solicitud->codigo_seguimiento,
        );

        // 4. Persistir referencias (SIREB manda el monto autoritativo)
        $solicitud->update([
            'referencia_recaudaciones' => $liquidacion['codigo_publico'],
            'liquidacion_id' => $liquidacion['id'],
            'monto_total' => $liquidacion['monto'],
            'datos_cobro_pendiente' => [
                'codigo_publico' => $liquidacion['codigo_publico'],
                'qr_string' => 'SIREB:'.$liquidacion['codigo_publico'],
                'qr_image_base64' => null,
                'monto' => $liquidacion['monto'],
                'fecha_vencimiento' => $liquidacion['fecha_vencimiento'],
                'items' => $liquidacion['items'],
            ],
        ]);

        $this->auditoria->registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'liquidacion_creada_sireb',
            null,
            ['estado' => 'pendiente'],
            [
                'liquidacion_id' => $liquidacion['id'],
                'codigo_publico' => $liquidacion['codigo_publico'],
                'monto' => $liquidacion['monto'],
            ],
        );
    }

    /**
     * Construye la respuesta al frontend a partir de la solicitud.
     * Compatible con SolicitudCreadaDTO sin depender del DTO de cobro.
     */
    private function construirRespuesta(SolicitudReserva $solicitud): SolicitudCreadaDTO
    {
        $datosCobro = $solicitud->datos_cobro_pendiente ?? [];

        // RespuestaCobroDTO legacy: qr_image_base64 y checkout_url ya no
        // aplican en SIREB v1 (solo pago manual). El qr_string se arma
        // localmente con el codigo_publico para que el frontend renderice
        // un QR escaneable/copiable en ventanilla.
        $respuestaCore = new \App\DTOs\RespuestaCobroDTO(
            referenciaRecaudaciones: $datosCobro['codigo_publico'] ?? $solicitud->codigo_seguimiento,
            qrString: $datosCobro['qr_string'] ?? null,
            qrImageBase64: null,
            checkoutUrl: null,
        );

        return new SolicitudCreadaDTO($solicitud, $respuestaCore);
    }

    /**
     * Capa 1: valida estado del campo, tarifa vigente, ventana de fechas,
     * horario de atención y disponibilidad de cada franja contra la grilla.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @return array<string, array{tarifas: Collection<string, \App\Models\TarifaCampo>, inicio_noche: int}>
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

            $tarifasActivas = $campo->tarifas()->whereNull('vigente_hasta')->get();
            if ($tarifasActivas->isEmpty()) {
                throw ValidationException::withMessages([
                    'franjas' => "El campo {$campo->nombre} no tiene tarifa vigente definida.",
                ]);
            }

            $campos[$campo->id] = [
                'tarifas' => $tarifasActivas->keyBy('tipo_tarifa'),
                'inicio_noche' => self::aSegundos($campo->hora_inicio_noche ?? '18:00:00'),
            ];

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
            fn (FranjaSolicitadaDTO $f) => $this->precioDeFranja($f, $campos[$f->campoId]),
        );
    }

    /**
     * Precio por hora de una franja según su tipo (diurna/nocturna).
     *
     * Decide comparando hora_inicio de la franja contra hora_inicio_noche
     * del campo. Si no existe la tarifa del tipo calculado (ej. un campo
     * con solo diurna), cae a la primera activa disponible.
     */
    private function precioDeFranja(FranjaSolicitadaDTO $franja, array $campoInfo): float
    {
        $tipo = self::aSegundos($franja->horaInicio) < $campoInfo['inicio_noche']
            ? 'diurna'
            : 'nocturna';

        $tarifa = $campoInfo['tarifas']->get($tipo) ?? $campoInfo['tarifas']->first();

        return (float) $tarifa->precio_por_hora;
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
