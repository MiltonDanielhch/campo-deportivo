<?php

namespace App\Jobs;

use App\DTOs\SolicitudCobroDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use App\Services\CatalogoSirebService;
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
 * Reintenta hasta 5 veces con delay de 60s. Si todas fallan,
 * marca la solicitud como rechazada con motivo 'error_cobro_inicial'.
 */
class ReintentarSolicitudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = 60;

    public function __construct(
        public readonly string $solicitudId,
    ) {
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping($this->solicitudId)];
    }

    public function handle(
        RecaudacionesApiClientInterface $client,
        CatalogoSirebService $catalogo,
        AuditoriaService $auditoria,
    ): void {
        $solicitud = SolicitudReserva::find($this->solicitudId);

        if (! $solicitud || $solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return;
        }

        // Si ya tiene liquidación, no hay nada que reintentar
        if (! empty($solicitud->liquidacion_id)) {
            return;
        }

        try {
            $this->crearLiquidacionEnSireb($solicitud, $client, $catalogo);

            $auditoria->registrar(
                'solicitudes_reserva',
                $solicitud->id,
                'liquidacion_creada_reintento',
                null,
                ['intentos' => $this->attempts()],
                ['liquidacion_id' => $solicitud->liquidacion_id],
            );
        } catch (RecaudacionesApiException $e) {
            Log::channel('sireb')->warning('Reintento fallido', [
                'solicitud_id' => $solicitud->id,
                'intento' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Si este fue el último intento, marcar rechazada
            if ($this->attempts() >= $this->tries) {
                $this->rechazarPorErrorCobro($solicitud, $e, $auditoria);
            }

            throw $e; // Laravel reintenta automáticamente
        }
    }

    private function crearLiquidacionEnSireb(
        SolicitudReserva $solicitud,
        RecaudacionesApiClientInterface $client,
        CatalogoSirebService $catalogo,
    ): void {
        // 1. Buscar o registrar cliente en SIREB
        $clienteSireb = $client->buscarCliente($solicitud->ci_nit_pagador);
        if (! $clienteSireb) {
            $clienteSireb = $client->registrarCliente([
                'ci_nit' => $solicitud->ci_nit_pagador,
                'nombre_completo' => $solicitud->nombre_pagador,
                'telefono' => $solicitud->telefono_pagador,
            ]);
        }

        // 2. Construir items (uno por franja con tarifa_id resuelto)
        $items = [];
        foreach ($solicitud->detalles as $detalle) {
            $campo = $detalle->campo;
            $tarifaId = $catalogo->resolverTarifaId($campo, $detalle->hora_inicio);
            $items[] = ['tarifa_id' => $tarifaId, 'cantidad' => 1];
        }

        // 3. Crear liquidación con idempotencia
        $idempotencyKey = "sedede:reserva:{$solicitud->id}";
        $liquidacion = $client->crearLiquidacion(
            items: $items,
            clienteId: $clienteSireb['id'],
            idempotencyKey: $idempotencyKey,
            referenciaExterna: $solicitud->codigo_seguimiento,
        );

        // 4. Persistir referencias
        $solicitud->update([
            'referencia_recaudaciones' => $liquidacion['codigo_publico'],
            'liquidacion_id' => $liquidacion['id'],
            'datos_cobro_pendiente' => [
                'codigo_publico' => $liquidacion['codigo_publico'],
                'monto' => $liquidacion['monto'],
                'fecha_vencimiento' => $liquidacion['fecha_vencimiento'],
                'items' => $liquidacion['items'],
            ],
        ]);
    }

    private function rechazarPorErrorCobro(
        SolicitudReserva $solicitud,
        RecaudacionesApiException $e,
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
            ['motivo_rechazo' => 'error_cobro_inicial', 'error' => $e->getMessage()],
        );
    }
}
