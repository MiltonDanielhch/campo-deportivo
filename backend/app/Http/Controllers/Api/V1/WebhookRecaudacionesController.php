<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\PiscinaReserva;
use App\Models\SolicitudReserva;
use App\Services\ConfirmacionCobroService;
use App\Services\Piscina\PiscinaConfirmacionCobroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de confirmación de cobro del Core de Recaudaciones (HU-D4).
 *
 * Vive fuera del namespace Public/ y fuera de auth:sanctum: no es
 * tráfico de la app móvil ni de un usuario logueado, es tráfico
 * servidor-a-servidor del Core. Su única puerta de entrada es la
 * verificación de firma (401 si falla).
 *
 * Un solo endpoint, un solo remitente posible: el Core.
 */
class WebhookRecaudacionesController extends Controller
{
    public function __construct(
        private RecaudacionesApiClientInterface $client,
        private ConfirmacionCobroService $confirmacion,
        private PiscinaConfirmacionCobroService $confirmacionPiscina,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        // 1. Verificar que la llamada realmente viene del Core
        if (! $this->client->verificarFirma($request)) {
            return response()->json(['message' => 'Firma inválida'], 401);
        }

        // 2. Parsear el payload
        $payload = $this->client->parsearWebhook($request);

        // 3. Buscar primero en SolicitudReserva (HU-D4, campos deportivos)
        $solicitud = $this->buscarSolicitud(
            $payload->referenciaRecaudaciones,
            $payload->referenciaExterna,
        );

        if ($solicitud) {
            // Confirmación idempotente de solicitud de campo
            $this->confirmacion->confirmar($solicitud, $payload->montoConfirmado);

            return response()->json(['message' => 'OK'], 200);
        }

        // 4. Si no se encontró, buscar en PiscinaReserva (módulo piscina)
        $reserva = $this->buscarReservaPiscina(
            $payload->referenciaRecaudaciones,
            $payload->referenciaExterna,
        );

        if ($reserva) {
            $this->confirmacionPiscina->confirmar($reserva, $payload->montoConfirmado);

            return response()->json(['message' => 'OK'], 200);
        }

        // 5. Si no existe: 200 igual (para que el Core no reintente
        //    indefinidamente) pero con log de advertencia
        Log::warning('Webhook Recaudaciones: solicitud/reserva no encontrada', [
            'referencia_recaudaciones' => $payload->referenciaRecaudaciones,
            'referencia_externa' => $payload->referenciaExterna,
        ]);

        return response()->json(['message' => 'OK'], 200);
    }

    private function buscarSolicitud(?string $referenciaRecaudaciones, ?string $referenciaExterna): ?SolicitudReserva
    {
        if ($referenciaRecaudaciones !== null) {
            $solicitud = SolicitudReserva::where('referencia_recaudaciones', $referenciaRecaudaciones)->first();
            if ($solicitud) {
                return $solicitud;
            }
        }

        if ($referenciaExterna !== null) {
            $solicitud = SolicitudReserva::where('codigo_seguimiento', $referenciaExterna)->first();
            if ($solicitud) {
                return $solicitud;
            }
        }

        return null;
    }

    private function buscarReservaPiscina(?string $referenciaRecaudaciones, ?string $referenciaExterna): ?PiscinaReserva
    {
        if ($referenciaRecaudaciones !== null) {
            $reserva = PiscinaReserva::where('referencia_recaudaciones', $referenciaRecaudaciones)->first();
            if ($reserva) {
                return $reserva;
            }
        }

        if ($referenciaExterna !== null) {
            $reserva = PiscinaReserva::where('codigo_seguimiento', $referenciaExterna)->first();
            if ($reserva) {
                return $reserva;
            }
        }

        return null;
    }
}
