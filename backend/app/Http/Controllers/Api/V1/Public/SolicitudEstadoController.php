<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\SolicitudEstadoResource;
use App\Models\SolicitudReserva;
use App\Services\ConfirmacionCobroService;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use Illuminate\Http\JsonResponse;

/**
 * Consulta pública del estado de una solicitud (HU-D7).
 * Sin autenticación: el ciudadano consulta con su código de seguimiento.
 */
class SolicitudEstadoController extends Controller
{
    public function __construct(
        private ConfirmacionCobroService $confirmacionCobro,
        private RecaudacionesApiClientInterface $sirebClient,
    ) {
    }

    public function show(string $codigoSeguimiento): JsonResponse
    {
        $solicitud = SolicitudReserva::where('codigo_seguimiento', $codigoSeguimiento)
            ->with('reservas.campo')
            ->first();

        if (! $solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        // Si está pendiente y tiene referencia de SIREB, verificar estado
        if ($solicitud->estado->value === 'pendiente' && $solicitud->referencia_recaudaciones) {
            try {
                $estadoSireb = $this->sirebClient->consultarLiquidacionPorCodigo(
                    $solicitud->referencia_recaudaciones
                );

                // Si SIREB indica que está pagada, confirmar la solicitud
                if ($estadoSireb && ($estadoSireb['estado'] ?? '') === 'pagada') {
                    $montoConfirmado = $estadoSireb['monto'] ?? null;
                    $this->confirmacionCobro->confirmar($solicitud, $montoConfirmado);

                    // Recargar la solicitud para obtener el estado actualizado
                    $solicitud->refresh();
                }
            } catch (\Exception $e) {
                // Si falla la consulta a SIREB, no bloquear: devolver el estado local
                \Log::channel('sireb')->warning('Error al verificar estado SIREB en consulta pública', [
                    'codigo_seguimiento' => $codigoSeguimiento,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => new SolicitudEstadoResource($solicitud),
        ]);
    }
}
