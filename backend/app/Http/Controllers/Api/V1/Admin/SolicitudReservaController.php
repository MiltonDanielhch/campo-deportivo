<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudFiltrosDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Exceptions\RecaudacionesApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularLiquidacionRequest;
use App\Http\Requests\StoreSolicitudReservaRequest;
use App\Http\Resources\AuditoriaResource;
use App\Http\Resources\SolicitudReservaAdminResource;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\Funcionario;
use App\DTOs\ServicioAdicionalDTO;
use App\Http\Requests\StoreRegularizacionRequest;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use App\Services\SolicitudReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    ) {}

    /**
     * GET /v1/admin/solicitudes-reserva
     * Listado admin con filtros, búsqueda, paginación y stats.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Funcionario $funcionario */
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
     * POST /v1/admin/solicitudes-reserva
     * Creación desde ventanilla: el funcionario genera la reserva y el código
     * de pago SIREB para un ciudadano que paga en caja. Usa el MISMO motor que
     * el canal público (disponibilidad, EXCLUDE, precio del espejo) pero sin el
     * gate anti-hoarding por IP, que es anti-abuso de anónimos, no de oficina.
     */
    public function store(StoreSolicitudReservaRequest $request): JsonResponse
    {
        /** @var Funcionario $funcionario */
        $funcionario = $request->user();

        if (! $this->solicitudes->esAdministrador($funcionario)) {
            return response()->json([
                'message' => 'Solo administradores pueden crear reservas desde el panel.',
            ], 403);
        }

        $v = $request->validated();

        $franjas = array_map(
            fn (array $f) => new FranjaSolicitadaDTO(
                campoId: $f['campo_id'],
                fecha: $f['fecha'],
                horaInicio: $f['hora_inicio'],
                horaFin: $f['hora_fin'],
                varianteId: $f['variante_id'] ?? null,
            ),
            $v['franjas'],
        );

        $servicios = array_map(
            fn (array $s) => new ServicioAdicionalDTO(
                campoId: $s['campo_id'],
                varianteId: $s['variante_id'],
                cantidad: (int) ($s['cantidad'] ?? 1),
            ),
            $v['servicios'] ?? [],
        );

        $datos = new SolicitudReservaDTO(
            franjas: $franjas,
            servicios: $servicios,
            nombrePagador: $v['nombre_pagador'],
            telefonoPagador: $v['telefono_pagador'] ?? null,
            ciNitPagador: $v['ci_nit_pagador'],
            ipCobrador: $request->ip(),
        );

        try {
            $resultado = $this->solicitudes->crear($datos, esAdmin: true);
        } catch (FranjaNoDisponibleException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        // Leemos el cobro del modelo fresco: no depende de los nombres del DTO.
        $solicitud = SolicitudReserva::findOrFail($resultado->solicitud->id);
        $cobro = $solicitud->datos_cobro_pendiente;

        $this->auditoria->registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'solicitud_creada_en_ventanilla',
            $funcionario->id,
            ['canal' => 'panel_admin'],
            [
                'codigo_seguimiento' => $solicitud->codigo_seguimiento,
                'monto_total' => $solicitud->monto_total,
            ],
        );

        return response()->json([
            'message' => 'Reserva creada. Entregá el código de pago al ciudadano.',
            'data' => [
                'codigo_seguimiento' => $solicitud->codigo_seguimiento,
                'monto_total' => (float) $solicitud->monto_total,
                'referencia_recaudaciones' => $cobro['codigo_publico'] ?? null,
                'qr_string' => $cobro['qr_string'] ?? null,
            ],
        ], 201);
    }

    /**
     * POST /v1/admin/solicitudes-reserva/regularizar
     *
     * [B77-B] Regulariza un uso que ocurrió pero no se pudo cobrar en el momento
     * (caída del sistema / SIREB down). Admin-only. Genera el código de pago
     * reusando el motor de venta, saltando solo los gates de tiempo de VENTA.
     */
    public function regularizar(StoreRegularizacionRequest $request): JsonResponse
    {
        /** @var Funcionario $funcionario */
        $funcionario = $request->user();

        if (! $this->solicitudes->esAdministrador($funcionario)) {
            return response()->json([
                'message' => 'Solo administradores pueden regularizar usos.',
            ], 403);
        }

        $v = $request->validated();

        $franjas = array_map(
            fn (array $f) => new FranjaSolicitadaDTO(
                campoId: $f['campo_id'],
                fecha: $f['fecha'],
                horaInicio: $f['hora_inicio'],
                horaFin: $f['hora_fin'],
                varianteId: $f['variante_id'] ?? null,
            ),
            $v['franjas'],
        );

        $servicios = array_map(
            fn (array $s) => new ServicioAdicionalDTO(
                campoId: $s['campo_id'],
                varianteId: $s['variante_id'],
                cantidad: (int) ($s['cantidad'] ?? 1),
            ),
            $v['servicios'] ?? [],
        );

        $datos = new SolicitudReservaDTO(
            franjas: $franjas,
            servicios: $servicios,
            nombrePagador: $v['nombre_pagador'],
            telefonoPagador: $v['telefono_pagador'] ?? '', // [FIX B77-B] DTO exige string, no null
            ciNitPagador: $v['ci_nit_pagador'],
            ipCobrador: $request->ip(),
        );

        try {
            $resultado = $this->solicitudes->regularizar($datos, $v['motivo'], $funcionario);
        } catch (FranjaNoDisponibleException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
        // [FIX B77-B] alineado con store(): solo se atrapa FranjaNoDisponible (409).
        // ValidationException → 422 automático de Laravel. Cualquier otra excepción
        // (SIREB down, etc.) NO se atrapa: en test muestra el stack real, en prod
        // Laravel loggea 500. El catch(\Throwable) anterior tapaba el error de verdad.

        $solicitud = SolicitudReserva::findOrFail($resultado->solicitud->id);
        $cobro = $solicitud->datos_cobro_pendiente;

        return response()->json([
            'message' => 'Uso regularizado. Entregá el código de pago al ciudadano.',
            'data' => [
                'codigo_seguimiento' => $solicitud->codigo_seguimiento,
                'monto_total' => (float) $solicitud->monto_total,
                'referencia_recaudaciones' => $cobro['codigo_publico'] ?? null,
                'qr_string' => $cobro['qr_string'] ?? null,
            ],
        ], 201);
    }

    /**
     * GET /v1/admin/solicitudes-reserva/{id}
     * Detalle admin con historial de auditoría y estado SIREB opcional.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Funcionario $funcionario */
        $funcionario = $request->user();

        $resultado = $this->solicitudes->obtenerAdmin($id, $funcionario);

        /** @var SolicitudReserva $solicitud */
        $solicitud = $resultado['solicitud'];

        /** @var Collection $auditoria */
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
