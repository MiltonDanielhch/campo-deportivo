<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularLiquidacionRequest;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints administrativos para gestión de solicitudes de reserva e integración SIREB.
 * Protegidos por rol admin_parametricas o admin_reservas.
 */
class SolicitudReservaController extends Controller
{
    public function __construct(
        private RecaudacionesApiClientInterface $sirebClient,
        private AuditoriaService $auditoria,
    ) {
    }

    /**
     * GET /v1/admin/solicitudes-reserva
     * Lista todas las solicitudes con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SolicitudReserva::with('detalles.campo');

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        if ($codigo = $request->query('codigo')) {
            $query->where('codigo_seguimiento', 'ilike', "%{$codigo}%");
        }

        if ($desde = $request->query('desde')) {
            $query->where('creado_en', '>=', $desde);
        }

        if ($hasta = $request->query('hasta')) {
            $query->where('creado_en', '<=', $hasta);
        }

        $solicitudes = $query
            ->orderByDesc('creado_en')
            ->paginate($request->query('per_page', 20));

        return response()->json($solicitudes);
    }

    /**
     * GET /v1/admin/solicitudes-reserva/{id}
     * Detalle completo con información de SIREB.
     */
    public function show(string $id): JsonResponse
    {
        $solicitud = SolicitudReserva::with(['detalles.campo', 'reservas'])->findOrFail($id);

        // Enriquecer con estado actual de SIREB si hay liquidación
        $estadoSireb = null;
        if ($solicitud->referencia_recaudaciones) {
            try {
                $estadoSireb = $this->sirebClient->consultarLiquidacionPorCodigo(
                    $solicitud->referencia_recaudaciones
                );
            } catch (\Exception $e) {
                Log::channel('sireb')->warning('No se pudo consultar estado SIREB', [
                    'solicitud_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => [
                'solicitud' => $solicitud,
                'estado_sireb' => $estadoSireb,
                'puede_anularse' => $this->puedeAnularse($solicitud, $estadoSireb),
            ],
        ]);
    }

    /**
     * POST /v1/admin/solicitudes-reserva/{id}/anular-liquidacion
     * Anula la liquidación en SIREB (si es posible) y marca la solicitud como cancelada.
     */
    public function anularLiquidacion(
        AnularLiquidacionRequest $request,
        string $id
    ): JsonResponse {
        $solicitud = SolicitudReserva::findOrFail($id);

        // Validaciones previas
        if (! $solicitud->liquidacion_id) {
            return response()->json([
                'message' => 'Esta solicitud no tiene liquidación creada en SIREB.',
            ], 422);
        }

        if ($solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return response()->json([
                'message' => 'Solo se pueden anular liquidaciones de solicitudes pendientes.',
            ], 422);
        }

        $motivo = $request->input('motivo');
        $funcionario = $request->user();

        try {
            $resultado = $this->sirebClient->anularLiquidacion(
                $solicitud->liquidacion_id,
                $motivo
            );

            // Éxito: marcar como cancelada localmente
            $solicitud->update([
                'estado' => EstadoSolicitudReserva::Cancelada,
                'motivo_rechazo' => 'anulacion_manual_admin',
            ]);

            $this->auditoria->registrar(
                'solicitudes_reserva',
                $solicitud->id,
                'anular_liquidacion_manual',
                $funcionario?->id,
                ['motivo' => $motivo],
                [
                    'liquidacion_id' => $solicitud->liquidacion_id,
                    'referencia_sireb' => $solicitud->referencia_recaudaciones,
                ],
            );

            return response()->json([
                'message' => 'Liquidación anulada correctamente.',
                'data' => $resultado,
            ]);
        } catch (RecaudacionesApiException $e) {
            // Regla de negocio CRÍTICA (Contexto Maestro):
            // Si SIREB responde LIQUIDACION_NO_ANULABLE, NO marcar como expirada/cancelada.
            // Dejarla pendiente para que el polling confirme el pago cuando se registre.
            if (str_contains($e->getMessage(), 'LIQUIDACION_NO_ANULABLE')) {
                $this->auditoria->registrar(
                    'solicitudes_reserva',
                    $solicitud->id,
                    'anulacion_intentada_no_anulable',
                    $funcionario?->id,
                    ['motivo' => $motivo],
                    ['error' => $e->getMessage()],
                );

                return response()->json([
                    'message' => 'No se puede anular: la liquidación ya tiene pago registrado.',
                    'codigo_error' => 'LIQUIDACION_NO_ANULABLE',
                ], 422);
            }

            Log::channel('sireb')->error('Error al anular liquidación', [
                'solicitud_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al contactar SIREB: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * GET /v1/admin/solicitudes-reserva/{id}/refrescar-sireb
     * Consulta el estado actual desde SIREB (útil cuando el polling se demora).
     */
    public function refrescarSireb(string $id): JsonResponse
    {
        $solicitud = SolicitudReserva::findOrFail($id);

        if (! $solicitud->referencia_recaudaciones) {
            return response()->json([
                'message' => 'Esta solicitud no tiene referencia de SIREB.',
            ], 422);
        }

        try {
            $estadoSireb = $this->sirebClient->consultarLiquidacionPorCodigo(
                $solicitud->referencia_recaudaciones
            );

            return response()->json([
                'data' => [
                    'estado_sireb' => $estadoSireb,
                    'puede_anularse' => $this->puedeAnularse($solicitud, $estadoSireb),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al consultar SIREB: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Determina si una liquidación puede anularse según reglas de SIREB v1:
     * - estado local = pendiente
     * - tiene liquidacion_id
     * - SIREB no reporta pago registrado
     */
    private function puedeAnularse(SolicitudReserva $solicitud, ?array $estadoSireb): bool
    {
        if ($solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return false;
        }

        if (! $solicitud->liquidacion_id) {
            return false;
        }

        // Si no hay info de SIREB, asumir anulable (intento de anular y manejar error)
        if ($estadoSireb === null) {
            return true;
        }

        // SIREB reporta pago → no anulable
        $pagado = ($estadoSireb['estado'] ?? '') === 'pagada'
            || ($estadoSireb['pagado'] ?? false) === true;

        return ! $pagado;
    }
}
