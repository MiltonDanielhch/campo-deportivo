<?php

namespace App\Jobs;

use App\Enums\EstadoSolicitudReserva;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use App\Services\CatalogoSirebService;
use App\Services\Sireb\SirebLiquidacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reintenta la creación de liquidación en SIREB cuando falló la primera vez.
 *
 * Delega la persistencia al helper central SirebLiquidacionService. Este job
 * solo decide la política ante el fallo:
 *   - attempts < tries -> lanza para que Laravel reintente (backoff 60s)
 *   - attempts >= tries -> rechazar solicitud + auditar + lanza
 *
 * Mantiene WithoutOverlapping para que no haya dos reintentos simultáneos de
 * la misma solicitud.
 */
class ReintentarSolicitudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $backoff = 60;

    public function __construct(
        public readonly string $solicitudId,
    ) {}

    public function middleware(): array
    {
        return [new WithoutOverlapping($this->solicitudId)];
    }

    public function handle(
        CatalogoSirebService $catalogo,
        AuditoriaService $auditoria,
        SirebLiquidacionService $liquidaciones,
    ): void {
        $solicitud = SolicitudReserva::find($this->solicitudId);

        if (! $solicitud || $solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return;
        }

        // Si ya tiene liquidación, no hay nada que reintentar.
        if (! empty($solicitud->liquidacion_id)) {
            return;
        }

        // ─── Validación defensiva: datos del pagador completos ───
        if (blank($solicitud->ci_nit_pagador) || blank($solicitud->nombre_pagador)) {
            Log::channel('sireb')->error('Solicitud sin datos de pagador completos, rechazando', [
                'solicitud_id' => $solicitud->id,
                'codigo_seguimiento' => $solicitud->codigo_seguimiento,
                'ci_nit_pagador' => $solicitud->ci_nit_pagador,
                'nombre_pagador' => $solicitud->nombre_pagador,
            ]);

            $this->rechazarPorDatosIncompletos($solicitud, $auditoria);

            return; // No reintentar, datos incompletos no se arreglan solos.
        }

        try {
            // Construir items (dominio de canchas: resuelve tarifa por campo/hora).
            $items = [];

            foreach ($solicitud->detalles as $detalle) {
                $campo = $detalle->campo;
                $tarifaId = $catalogo->resolverTarifaId($campo, $detalle->hora_inicio);

                $items[] = [
                    'tarifa_id' => $tarifaId,
                    'cantidad' => 1,
                ];
            }

            // Delegar al helper central: cliente + liquidación + persistencia + auditoría.
            $liquidaciones->crearYPersistir(
                reserva: $solicitud,
                items: $items,
                idempotencyPrefix: 'sedede:reserva',
                tablaAuditoria: 'solicitudes_reserva',
                accionAuditoria: 'liquidacion_creada_reintento',
                antesAuditoria: ['intentos' => $this->attempts()],
            );
        } catch (\Throwable $e) {
            Log::channel('sireb')->warning('Reintento fallido', [
                'solicitud_id' => $solicitud->id,
                'intento' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->rechazarPorErrorCobro($solicitud, $e, $auditoria);
            }

            throw $e;
        }
    }

    private function rechazarPorErrorCobro(
        SolicitudReserva $solicitud,
        \Throwable $e,
        AuditoriaService $auditoria,
    ): void {
        $solicitud->update([
            'estado' => EstadoSolicitudReserva::Rechazada,
            'motivo_rechazo' => 'error_cobro_inicial',
        ]);

        $auditoria->registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'rechazada_error_cobro',
            null,
            ['intentos_realizados' => $this->tries],
            [
                'motivo_rechazo' => 'error_cobro_inicial',
                'error' => $e->getMessage(),
            ],
        );
    }

    private function rechazarPorDatosIncompletos(
        SolicitudReserva $solicitud,
        AuditoriaService $auditoria,
    ): void {
        $solicitud->update([
            'estado' => EstadoSolicitudReserva::Rechazada,
            'motivo_rechazo' => 'datos_pagador_incompletos',
        ]);

        $auditoria->registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'rechazada_datos_incompletos',
            null,
            [],
            [
                'motivo_rechazo' => 'datos_pagador_incompletos',
                'ci_nit_pagador' => $solicitud->ci_nit_pagador,
                'nombre_pagador' => $solicitud->nombre_pagador,
            ],
        );
    }
}
