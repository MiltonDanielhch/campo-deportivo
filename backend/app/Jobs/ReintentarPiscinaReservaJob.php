<?php

namespace App\Jobs;

use App\Enums\EstadoPiscinaReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\PiscinaReserva;
use App\Services\AuditoriaService;
use App\Services\Piscina\PiscinaReservaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reintenta crear liquidación en SIREB si falló inicialmente.
 */
class ReintentarPiscinaReservaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_REINTENTOS = 5;
    public const RETRASO_SEGUNDOS = 60;

    public function __construct(
        public readonly string $reservaId,
        public readonly int $intento = 1,
    ) {
        $this->onQueue('sireb-reintentos');
    }

    public function handle(
        RecaudacionesApiClientInterface $client,
        AuditoriaService $auditoria,
    ): void {
        $reserva = PiscinaReserva::find($this->reservaId);

        if (!$reserva || $reserva->estado !== EstadoPiscinaReserva::Pendiente) {
            return;
        }

        // Si ya tiene liquidación, no reintentar
        if ($reserva->liquidacion_id) {
            return;
        }

        try {
            // Buscar cliente en SIREB
            $clienteSireb = $client->buscarCliente($reserva->ci_nit_pagador);
            if (!$clienteSireb) {
                $clienteSireb = $client->registrarCliente([
                    'ci_nit' => $reserva->ci_nit_pagador,
                    'nombre_completo' => $reserva->nombre_pagador,
                    'telefono' => $reserva->telefono_pagador,
                ]);
            }

            // Construir items para SIREB
            $items = [];
            foreach ($reserva->detalles as $detalle) {
                $items[] = [
                    'tarifa_id' => $detalle->tarifa_sireb_id,
                    'cantidad' => $detalle->cantidad,
                ];
            }

            // Crear liquidación con idempotencia
            $idempotencyKey = "sedede:piscina:{$reserva->id}";
            $liquidacion = $client->crearLiquidacion(
                items: $items,
                clienteId: $clienteSireb['id'],
                idempotencyKey: $idempotencyKey,
                referenciaExterna: $reserva->codigo_seguimiento,
            );

            // Actualizar reserva
            $reserva->update([
                'referencia_recaudaciones' => $liquidacion['codigo_publico'],
                'liquidacion_id' => $liquidacion['id'],
                'monto_total' => $liquidacion['monto'],
                'datos_cobro_pendiente' => [
                    'codigo_publico' => $liquidacion['codigo_publico'],
                    'qr_string' => 'SIREB:' . $liquidacion['codigo_publico'],
                    'qr_image_base64' => null,
                    'monto' => $liquidacion['monto'],
                    'fecha_vencimiento' => $liquidacion['fecha_vencimiento'],
                    'items' => $liquidacion['items'],
                ],
            ]);

            $auditoria->registrar(
                'piscina_reservas',
                $reserva->id,
                'liquidacion_creada_sireb',
                null,
                ['estado' => 'pendiente'],
                [
                    'liquidacion_id' => $liquidacion['id'],
                    'codigo_publico' => $liquidacion['codigo_publico'],
                    'monto' => $liquidacion['monto'],
                ],
            );

            Log::channel('sireb')->info('Liquidación creada exitosamente en SIREB (reintento)', [
                'reserva_id' => $reserva->id,
                'intento' => $this->intento,
            ]);

        } catch (\Throwable $e) {
            Log::channel('sireb')->error('Error al crear liquidación SIREB (reintento)', [
                'reserva_id' => $reserva->id,
                'intento' => $this->intento,
                'error' => $e->getMessage(),
            ]);

            // Reintentar si no excedió el máximo
            if ($this->intento < self::MAX_REINTENTOS) {
                self::dispatch($this->reservaId, $this->intento + 1)
                    ->delay(now()->addSeconds(self::RETRASO_SEGUNDOS));
            } else {
                // Rechazar reserva después de máximos reintentos
                $reserva->update([
                    'estado' => EstadoPiscinaReserva::Rechazada,
                    'motivo_rechazo' => 'error_cobro_inicial',
                ]);

                // Liberar cupos
                foreach ($reserva->detalles as $detalle) {
                    $perfilTurno = $detalle->turno->perfiles()
                        ->where('perfil', $detalle->perfil)
                        ->first();

                    if ($perfilTurno) {
                        $perfilTurno->decrement('reservado', $detalle->cantidad);
                    }
                }

                $auditoria->registrar(
                    'piscina_reservas',
                    $reserva->id,
                    'rechazar_por_error_cobro',
                    null,
                    ['estado' => 'pendiente'],
                    ['estado' => 'rechazada', 'motivo_rechazo' => 'error_cobro_inicial'],
                );
            }
        }
    }
}
