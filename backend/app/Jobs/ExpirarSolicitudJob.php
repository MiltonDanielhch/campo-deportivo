<?php

namespace App\Jobs;

use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ParametroSistema;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Expira una solicitud pendiente al vencer su timer.
 *
 * Antes de expirar localmente intenta anular la liquidación en SIREB
 * (HU-047: SIREB solo permite anular si está pendiente y sin pago activo).
 *
 * Regla de conciliación:
 *  - Anulación OK            → expirar localmente.
 *  - LIQUIDACION_NO_ANULABLE → la liquidación YA tiene pago: NO expirar.
 *    Se deja pendiente y el polling confirmará cuando SIREB valide el pago.
 *  - Otro error (5xx, red)   → expirar localmente igual y loguear error.
 */
class ExpirarSolicitudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $solicitudId,
    ) {
    }

    public function handle(RecaudacionesApiClientInterface $client): void
    {
        $solicitud = SolicitudReserva::find($this->solicitudId);

        if (! $solicitud || $solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return;
        }

        // Intentar anular en SIREB (si tiene liquidacion_id)
        $sePuedeExpirar = true;
        if (! empty($solicitud->liquidacion_id)) {
            $sePuedeExpirar = $this->intentarAnularEnSireb($solicitud, $client);
        }

        // Marcar como expirada solo si SIREB lo permite
        if ($sePuedeExpirar) {
            $solicitud->update([
                'estado' => EstadoSolicitudReserva::Expirada,
                'motivo_rechazo' => 'expirada_sin_pago',
            ]);

            AuditoriaService::registrar(
                'solicitudes_reserva',
                $solicitud->id,
                'expirar_automaticamente',
                null,
                ['estado' => 'pendiente'],
                ['estado' => 'expirada', 'motivo_rechazo' => 'expirada_sin_pago'],
            );

            // ← SIEMPRE registrar expiración para M3 (anti-hoarding)
            if ($solicitud->ip_cobrador) {
                $this->registrarExpiracionIp($solicitud->ip_cobrador);
            }
        }
    }

    /**
     * Incrementa el contador de expiraciones de la IP (TTL 1 hora). Si llega al
     * umbral, bloquea la IP por los minutos configurados en parametros_sistema.
     */
    private function registrarExpiracionIp(string $ip): void
    {
        $umbral = (int) (ParametroSistema::where('clave', 'umbral_expiraciones_cooldown')->value('valor') ?: 3);
        $cooldownMin = (int) (ParametroSistema::where('clave', 'cooldown_expiracion_minutos')->value('valor') ?: 30);

        $counterKey = 'hoard:counter:'.md5($ip);
        $cooldownKey = 'hoard:cooldown:'.md5($ip);

        $ttlContadorSeg = 3600;
        $ttlCooldownSeg = $cooldownMin * 60;

        // Obtener el valor actual del cache (puede ser null si no existe)
        $contadorActual = Cache::get($counterKey);

        // Si es null, empezar desde 0; si no, incrementar
        $contador = ($contadorActual === null) ? 1 : ((int) $contadorActual + 1);

        // Guardar el nuevo valor con TTL
        Cache::put($counterKey, $contador, $ttlContadorSeg);

        if ($contador >= $umbral) {
            Cache::put($cooldownKey, $cooldownMin, $ttlCooldownSeg);

            Log::channel('sireb')->warning('IP bloqueada por hoarding (expiraciones repetidas)', [
                'ip_hash' => md5($ip),
                'expiraciones_ultima_hora' => $contador,
                'cooldown_minutos' => $cooldownMin,
            ]);
        }
    }

    /**
     * true  = se puede expirar localmente (anulación OK o error tolerable).
     * false = la liquidación ya tiene pago activo: NO expirar.
     */
    private function intentarAnularEnSireb(
        SolicitudReserva $solicitud,
        RecaudacionesApiClientInterface $client,
    ): bool {
        try {
            $client->anularLiquidacion(
                $solicitud->liquidacion_id,
                'solicitud_expirada_sin_pago',
            );

            Log::channel('sireb')->info('Liquidación anulada en SIREB por expiración', [
                'solicitud_id' => $solicitud->id,
                'liquidacion_id' => $solicitud->liquidacion_id,
            ]);

            return true;
        } catch (RecaudacionesApiException $e) {
            if (str_contains($e->getMessage(), 'LIQUIDACION_NO_ANULABLE')) {
                Log::channel('sireb')->warning(
                    'No se anula: la liquidación ya tiene pago activo. Se deja pendiente para confirmación por polling.',
                    [
                        'solicitud_id' => $solicitud->id,
                        'liquidacion_id' => $solicitud->liquidacion_id,
                    ],
                );

                return false;
            }

            Log::channel('sireb')->error(
                'Error al anular en SIREB; se expira localmente igual',
                [
                    'solicitud_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ],
            );

            return true;
        }
    }
}
