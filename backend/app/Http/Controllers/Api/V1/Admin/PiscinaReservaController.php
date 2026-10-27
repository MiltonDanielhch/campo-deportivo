<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\PiscinaReservaDTO;
use App\Enums\EstadoPiscinaReserva;
use App\Exceptions\FranjaNoDisponibleException;
use App\Exceptions\RecaudacionesApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Piscina\StorePiscinaReservaRequest;
use App\Models\PiscinaReserva;
use App\Services\Piscina\PiscinaReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\AuditoriaResource;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\Auditoria;

/**
 * Controller admin para gestión de reservas de piscina.
 */
class PiscinaReservaController extends Controller
{
    public function __construct(
        private PiscinaReservaService $reservas,
        private RecaudacionesApiClientInterface $sirebClient,
    ) {}

    /**
     * GET /api/v1/admin/piscina/reservas
     *
     * Lista reservas de piscina con filtros, búsqueda, paginación y stats.
     * El contrato de `meta.stats` espeja el de solicitudes-reserva para que el
     * panel pueda reutilizar las mismas tarjetas de resumen.
     */
    public function index(Request $request): JsonResponse
    {
        $statsRow = $this->aplicarFiltros(PiscinaReserva::query(), $request)
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when estado = 'pendiente' then 1 else 0 end) as pendientes")
            ->selectRaw("sum(case when estado = 'confirmada' then 1 else 0 end) as confirmadas")
            ->selectRaw("sum(case when estado = 'expirada' then 1 else 0 end) as expiradas")
            ->selectRaw("sum(case when estado = 'cancelada' then 1 else 0 end) as canceladas")
            ->selectRaw("sum(case when estado = 'rechazada' then 1 else 0 end) as rechazadas")
            ->selectRaw("coalesce(sum(case when estado = 'confirmada' then monto_confirmado else 0 end), 0) as monto_confirmado")
            ->first();

        $stats = [
            'total' => (int) $statsRow->total,
            'pendientes' => (int) $statsRow->pendientes,
            'confirmadas' => (int) $statsRow->confirmadas,
            'expiradas' => (int) $statsRow->expiradas,
            'canceladas' => (int) $statsRow->canceladas,
            'rechazadas' => (int) $statsRow->rechazadas,
            'monto_confirmado' => (float) $statsRow->monto_confirmado,
        ];

        $reservas = $this->aplicarFiltros(
            PiscinaReserva::with(['detalles.turno', 'campo']),
            $request,
        )
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => $reservas->items(),
            'meta' => [
                'current_page' => $reservas->currentPage(),
                'per_page' => $reservas->perPage(),
                'total' => $reservas->total(),
                'last_page' => $reservas->lastPage(),
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * POST /v1/admin/piscina/reservas
     * Creación desde ventanilla: el funcionario genera la reserva y el código
     * de pago SIREB para el ciudadano. Usa el mismo motor que el público,
     * pero si SIREB falla, rechaza inline y libera el cupo.
     */
    public function store(StorePiscinaReservaRequest $request): JsonResponse
    {
        $v = $request->validated();

        $dto = new PiscinaReservaDTO(
            fecha: $v['fecha'],
            turnoId: $v['turno_id'],
            items: $v['items'],
            nombrePagador: $v['nombre_pagador'],
            telefonoPagador: $v['telefono_pagador'] ?? '',
            ciNitPagador: $v['ci_nit_pagador'],
            ipCobrador: $request->ip() ?? '127.0.0.1',
        );

        try {
            $resultado = $this->reservas->crear($dto, esAdmin: true);
        } catch (FranjaNoDisponibleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'cupo_no_disponible',
            ], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (RecaudacionesApiException $e) {
            return response()->json([
                'message' => 'No se pudo generar el código de pago: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::channel('sireb')->error('Error inesperado creando reserva de piscina desde ventanilla', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error interno al crear la reserva de piscina.',
            ], 500);
        }

        return response()->json([
            'message' => 'Reserva creada. Entregá el código de pago al ciudadano.',
            'data' => $resultado,
        ], 201);
    }

    /**
     * Aplica los filtros comunes del listado (y de las stats) sobre la query.
     */
    private function aplicarFiltros($query, Request $request)
    {
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('campo_id')) {
            $query->where('campo_id', $request->input('campo_id'));
        }

        if ($request->filled('fecha_desde')) {
            $query->where('created_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('created_at', '<=', $request->input('fecha_hasta').' 23:59:59');
        }

        if ($request->filled('codigo_seguimiento')) {
            $query->where('codigo_seguimiento', 'like', '%'.$request->input('codigo_seguimiento').'%');
        }

        if ($request->filled('buscar')) {
            $termino = $request->input('buscar');
            $query->where(function ($q) use ($termino) {
                $q->where('codigo_seguimiento', 'like', "%{$termino}%")
                    ->orWhere('nombre_pagador', 'like', "%{$termino}%")
                    ->orWhere('ci_nit_pagador', 'like', "%{$termino}%")
                    ->orWhere('telefono_pagador', 'like', "%{$termino}%")
                    ->orWhere('referencia_recaudaciones', 'like', "%{$termino}%");
            });
        }

        return $query;
    }

    /**
     * GET /api/v1/admin/piscina/reservas/{id}
     *
     * Detalle admin con historial de auditoría y estado SIREB.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $reserva = PiscinaReserva::with(['detalles.turno.perfiles', 'campo'])
            ->find($id);

        if (! $reserva) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }

        $auditoria = Auditoria::with('usuario')
            ->where('tabla', 'piscina_reservas')
            ->where('registro_id', $reserva->id)
            ->orderBy('fecha')
            ->get();

        $estadoSireb = null;

        if ($reserva->referencia_recaudaciones) {
            try {
                $estadoSireb = $this->sirebClient->consultarLiquidacionPorCodigo(
                    $reserva->referencia_recaudaciones
                );
            } catch (\Exception $e) {
                Log::channel('sireb')->warning('No se pudo consultar estado SIREB en piscina', [
                    'reserva_id' => $reserva->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => [
                'reserva' => $reserva,
                'auditoria' => $auditoria
                    ->map(fn ($entrada) => (new AuditoriaResource($entrada))->resolve($request))
                    ->all(),
                'estado_sireb' => $estadoSireb,
                'puede_anularse' => false,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/piscina/reservas/{id}/refrescar-sireb
     *
     * Consulta el estado actual desde SIREB.
     */
    public function refrescarSireb(string $id): JsonResponse
    {
        $reserva = PiscinaReserva::find($id);

        if (! $reserva) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }

        if (! $reserva->referencia_recaudaciones) {
            return response()->json([
                'message' => 'Esta reserva no tiene referencia de SIREB.',
            ], 422);
        }

        try {
            $estadoSireb = $this->sirebClient->consultarLiquidacionPorCodigo(
                $reserva->referencia_recaudaciones
            );

            return response()->json([
                'data' => [
                    'estado_sireb' => $estadoSireb,
                    'puede_anularse' => false,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al consultar SIREB: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * POST /api/v1/admin/piscina/reservas/{id}/cancelar
     *
     * Cancela una reserva de piscina.
     */
    public function cancelar(Request $request, string $id): JsonResponse
    {
        $reserva = PiscinaReserva::find($id);
        if (! $reserva) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }

        $validated = $request->validate([
            'motivo' => ['required', 'string'],
        ]);

        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        try {
            $this->reservas->cancelar($reserva, $validated['motivo'], $funcionario->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::channel('sireb')->error('Error al cancelar reserva de piscina', [
                'reserva_id' => $reserva->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error al cancelar reserva'], 500);
        }

        return response()->json([
            'message' => 'Reserva cancelada exitosamente',
            'data'    => $reserva->fresh()->load('detalles'),
        ]);
    }

    /**
     * GET /api/v1/admin/piscina/reservas/exportar-csv
     */
    public function exportarCsv(Request $request)
    {
        $fileName = 'reservas-piscina-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($request) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($this->reservas->exportarCsv($request) as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
