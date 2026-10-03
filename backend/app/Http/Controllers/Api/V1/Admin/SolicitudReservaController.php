<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\SolicitudFiltrosDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularLiquidacionRequest;
use App\Http\Resources\AuditoriaResource;
use App\Http\Resources\SolicitudReservaAdminResource;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use App\Services\SolicitudReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints administrativos para gestión de solicitudes de reserva e integración SIREB.
 *
 * Fase 7.1:
 * - index() y show() son lectura para admin_parametricas, admin_reservas y funcionario_control.
 * - funcionario_control solo ve sus campos asignados, server-side.
 * - anularLiquidacion() y refrescarSireb() siguen siendo solo para admins.
 */
class SolicitudReservaController extends Controller
{
    public function __construct(
        private RecaudacionesApiClientInterface $sirebClient,
        private AuditoriaService $auditoria,
        private SolicitudReservaService $solicitudes,
    ) {
    }

    /**
     * GET /v1/admin/solicitudes-reserva
     * Listado admin con filtros, búsqueda, paginación y stats.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        $filtros = SolicitudFiltrosDTO::fromRequest($request);

        $solicitudes = $this->solicitudes->listarAdmin($filtros, $funcionario);
        $stats = $this->solicitudes->calcularStatsAdmin($filtros, $funcionario);

        $data = $solicitudes->getCollection()
            ->map(fn (SolicitudReserva $solicitud) => (new SolicitudReservaAdminResource($solicitud))->resolve($request))
            ->all();

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $solicitudes->url(1),
                'last' => $solicitudes->url($solicitudes->lastPage()),
                'prev' => $solicitudes->previousPageUrl(),
                'next' => $solicitudes->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $solicitudes->currentPage(),
                'last_page' => $solicitudes->lastPage(),
                'total' => $solicitudes->total(),
                'per_page' => $solicitudes->perPage(),
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * GET /v1/admin/solicitudes-reserva/{id}
     * Detalle admin con historial de auditoría y estado SIREB opcional.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        $resultado = $this->solicitudes->obtenerAdmin($id, $funcionario);

        /** @var SolicitudReserva $solicitud */
        $solicitud = $resultado['solicitud'];

        /** @var \Illuminate\Support\Collection $auditoria */
        $auditoria = $resultado['auditoria'];

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

        $esAdmin = $this->solicitudes->esAdministrador($funcionario);

        return response()->json([
            'data' => [
                'solicitud' => (new SolicitudReservaAdminResource($solicitud))->resolve($request),
                'auditoria' => $auditoria
                    ->map(fn ($entrada) => (new AuditoriaResource($entrada))->resolve($request))
                    ->all(),
                'estado_sireb' => $estadoSireb,
                'puede_anularse' => $esAdmin && $this->puedeAnularse($solicitud, $estadoSireb),
            ],
        ]);
    }

    /**
     * POST /v1/admin/solicitudes-reserva/{id}/anular-liquidacion
     * Anula la liquidación en SIREB (si es posible) y marca la solicitud como cancelada.
     *
     * Regla crítica: si SIREB responde LIQUIDACION_NO_ANULABLE, NO cancelar localmente.
     */
    public function anularLiquidacion(
        AnularLiquidacionRequest $request,
        string $id
    ): JsonResponse {
        $solicitud = SolicitudReserva::findOrFail($id);

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
                'message' => 'Error al contactar SIREB: '.$e->getMessage(),
            ], 503);
        }
    }

    /**
     * GET /v1/admin/solicitudes-reserva/{id}/refrescar-sireb
     * Consulta el estado actual desde SIREB.
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
                'message' => 'Error al consultar SIREB: '.$e->getMessage(),
            ], 503);
        }
    }

    /**
     * Determina si una liquidación puede anularse según reglas de SIREB v1.
     */
    private function puedeAnularse(SolicitudReserva $solicitud, ?array $estadoSireb): bool
    {
        if ($solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return false;
        }

        if (! $solicitud->liquidacion_id) {
            return false;
        }

        if ($estadoSireb === null) {
            return true;
        }

        $pagado = ($estadoSireb['estado'] ?? '') === 'pagada'
            || ($estadoSireb['pagado'] ?? false) === true;

        return ! $pagado;
    }
}
