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

use App\DTOs\SolicitudFiltrosDTO;
use App\Models\Auditoria;
use App\Models\Funcionario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Generator;

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


    public function esAdministrador(Funcionario $funcionario): bool
    {
        return $funcionario->tienePermiso('*')
            || in_array($funcionario->rol?->nombre, ['admin_parametricas', 'admin_reservas'], true);
    }

    public function listarAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): LengthAwarePaginator
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        return $this->consultaBaseAdmin($filtros, $funcionario)
            ->paginate(
                $filtros->perPage,
                ['*'],
                'page',
                $filtros->page
            );
    }

    public function calcularStatsAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): array
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        $query = $this->consultaBaseAdmin($filtros, $funcionario, incluirEstado: false);

        $pendiente = EstadoSolicitudReserva::Pendiente->value;
        $confirmada = EstadoSolicitudReserva::Confirmada->value;
        $expirada = EstadoSolicitudReserva::Expirada->value;
        $cancelada = EstadoSolicitudReserva::Cancelada->value;
        $rechazada = EstadoSolicitudReserva::Rechazada->value;

        $stats = $query->getQuery()
            ->clone()
            ->reorder()
            ->selectRaw("
                count(*) as total,
                count(*) filter (where estado = '{$pendiente}') as pendientes,
                count(*) filter (where estado = '{$confirmada}') as confirmadas,
                count(*) filter (where estado = '{$expirada}') as expiradas,
                count(*) filter (where estado = '{$cancelada}') as canceladas,
                count(*) filter (where estado = '{$rechazada}') as rechazadas,
                coalesce(sum(monto_confirmado) filter (where estado = '{$confirmada}'), 0) as monto_confirmado
            ")
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'pendientes' => (int) ($stats->pendientes ?? 0),
            'confirmadas' => (int) ($stats->confirmadas ?? 0),
            'expiradas' => (int) ($stats->expiradas ?? 0),
            'canceladas' => (int) ($stats->canceladas ?? 0),
            'rechazadas' => (int) ($stats->rechazadas ?? 0),
            'monto_confirmado' => (float) ($stats->monto_confirmado ?? 0),
        ];
    }

    /**
     * Genera filas CSV aplanadas: una fila por detalle/franja.
     *
     * Usa lazy/chunk para no cargar todo el dataset en memoria.
     *
     * @return Generator<int, array<int, string>>
     */
    public function exportarCsv(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): Generator
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        $query = $this->consultaBaseAdmin($filtros, $funcionario);

        yield [
            'codigo_seguimiento',
            'estado',
            'nombre_pagador',
            'telefono_pagador',
            'ci_nit_pagador',
            'codigo_reserva',
            'campo_nombre',
            'fecha_reserva',
            'hora_inicio',
            'hora_fin',
            'monto_pagado',
            'asistencia_marcada_en',
        ];

        foreach ($query->lazy(500) as $solicitud) {
            foreach ($solicitud->detalles as $detalle) {
                $reserva = $detalle->reserva;

                $montoPagado = '';

                if ($reserva && $reserva->monto_pagado !== null) {
                    $montoPagado = number_format((float) $reserva->monto_pagado, 2, '.', '');
                }

                yield [
                    (string) $solicitud->codigo_seguimiento,
                    $solicitud->estado->value,
                    (string) $solicitud->nombre_pagador,
                    (string) $solicitud->telefono_pagador,
                    (string) $solicitud->ci_nit_pagador,
                    (string) ($reserva?->codigo_reserva ?? ''),
                    (string) ($detalle->campo?->nombre ?? ''),
                    (string) ($detalle->fecha_reserva?->format('Y-m-d') ?? ''),
                    (string) substr((string) $detalle->hora_inicio, 0, 5),
                    (string) substr((string) $detalle->hora_fin, 0, 5),
                    $montoPagado,
                    (string) ($reserva?->asistencia_marcada_en?->format('Y-m-d H:i:s') ?? ''),
                ];
            }
        }
    }

    /**
     * @return array{solicitud: \App\Models\SolicitudReserva, auditoria: \Illuminate\Support\Collection<int, \App\Models\Auditoria>}
     */
    public function obtenerAdmin(string $id, Funcionario $funcionario): array
    {
        $esAdmin = $this->esAdministrador($funcionario);

        $solicitud = SolicitudReserva::with([
            'detalles',
            'detalles.campo',
            'detalles.reserva',
        ])->find($id);

        if (! $solicitud) {
            abort(404);
        }

        if (! $esAdmin) {
            $tieneAcceso = $solicitud->detalles()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->exists();

            if (! $tieneAcceso) {
                abort(403);
            }

            $detallesVisibles = $solicitud->detalles()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->with(['campo', 'reserva'])
                ->get();

            $solicitud->setRelation('detalles', $detallesVisibles);
        }

        $auditoria = Auditoria::where('tabla', 'solicitudes_reserva')
            ->where('registro_id', $solicitud->id)
            ->with('usuario')
            ->orderBy('fecha')
            ->get();

        return [
            'solicitud' => $solicitud,
            'auditoria' => $auditoria,
        ];
    }

    private function forzarFiltrosParaFuncionarioControl(
        SolicitudFiltrosDTO $filtros,
        Funcionario $funcionario
    ): void {
        if (! $this->esAdministrador($funcionario)) {
            $filtros->funcionarioControlId = $funcionario->id;
        }
    }

    private function consultaBaseAdmin(
        SolicitudFiltrosDTO $filtros,
        Funcionario $funcionario,
        bool $incluirEstado = true
    ): Builder {
        $esAdmin = $this->esAdministrador($funcionario);

        $query = SolicitudReserva::query();

        if ($esAdmin) {
            $query->with([
                'detalles',
                'detalles.campo',
                'detalles.reserva',
            ]);
        } else {
            $query->with([
                'detalles' => function ($q) use ($funcionario) {
                    $q->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                        $aq->where('funcionario_id', $funcionario->id);
                    });
                },
                'detalles.campo',
                'detalles.reserva',
            ]);
        }

        if ($incluirEstado && $filtros->estado) {
            $query->where('estado', $filtros->estado);
        }

        if ($filtros->desde) {
            $query->where('creado_en', '>=', $filtros->desde->startOfDay());
        }

        if ($filtros->hasta) {
            $query->where('creado_en', '<=', $filtros->hasta->endOfDay());
        }

        if ($filtros->campoId) {
            if ($esAdmin) {
                $query->whereHas('detalles', function ($q) use ($filtros) {
                    $q->where('campo_id', $filtros->campoId);
                });
            } else {
                $query->whereHas('detalles', function ($q) use ($filtros, $funcionario) {
                    $q->where('campo_id', $filtros->campoId)
                        ->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                            $aq->where('funcionario_id', $funcionario->id);
                        });
                });
            }
        }

        if ($filtros->funcionarioControlId) {
            $query->whereHas('detalles.campo.asignacionesFuncionario', function ($q) use ($filtros) {
                $q->where('funcionario_id', $filtros->funcionarioControlId);
            });
        }

        if ($filtros->buscar) {
            $termino = '%'.$this->escaparPatronLike($filtros->buscar).'%';

            $query->where(function ($q) use ($termino) {
                $q->whereRaw('codigo_seguimiento ILIKE ?', [$termino])
                    ->orWhereRaw('nombre_pagador ILIKE ?', [$termino])
                    ->orWhereRaw('telefono_pagador ILIKE ?', [$termino])
                    ->orWhereRaw('ci_nit_pagador ILIKE ?', [$termino]);
            });
        }

        return $query
            ->orderByDesc('creado_en')
            ->orderByDesc('id');
    }

    private function escaparPatronLike(string $valor): string
    {
        return addcslashes($valor, '\\%_');
    }
}
