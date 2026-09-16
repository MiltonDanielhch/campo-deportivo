<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\SolicitudReserva;
use App\Services\ConfirmacionCobroService;
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

        // 3. Buscar la solicitud por cualquiera de las dos referencias
        $solicitud = null;

        if ($payload->referenciaRecaudaciones !== null) {
            $solicitud = SolicitudReserva::where(
                'referencia_recaudaciones',
                $payload->referenciaRecaudaciones,
            )->first();
        }

        if (! $solicitud && $payload->referenciaExterna !== null) {
            $solicitud = SolicitudReserva::where(
                'codigo_seguimiento',
                $payload->referenciaExterna,
            )->first();
        }

        // 4. Si no existe: 200 igual (para que el Core no reintente
        //    indefinidamente) pero con log de advertencia
        if (! $solicitud) {
            Log::warning('Webhook Recaudaciones: solicitud no encontrada', [
                'referencia_recaudaciones' => $payload->referenciaRecaudaciones,
                'referencia_externa' => $payload->referenciaExterna,
            ]);

            return response()->json(['message' => 'OK'], 200);
        }

        // 5. Confirmación idempotente (misma lógica que el polling)
        $this->confirmacion->confirmar($solicitud, $payload->montoConfirmado);

        return response()->json(['message' => 'OK'], 200);
    }
}
