<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EstadoPiscinaReserva;
use App\Http\Controllers\Controller;
use App\Models\PiscinaReserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller admin para gestión de reservas de piscina.
 */
class PiscinaReservaController extends Controller
{
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
     * Obtiene detalle de una reserva de piscina.
     */
    public function show(string $id): JsonResponse
    {
        $reserva = PiscinaReserva::with(['detalles.turno.perfiles', 'campo'])
            ->find($id);

        if (!$reserva) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }

        return response()->json([
            'data' => $reserva,
        ]);
    }

    /**
     * POST /api/v1/admin/piscina/reservas/{id}/cancelar
     *
     * Cancela una reserva de piscina.
     */
    public function cancelar(Request $request, string $id): JsonResponse
    {
        $reserva = PiscinaReserva::find($id);
        if (!$reserva) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }

        // Solo se pueden cancelar reservas pendientes
        if ($reserva->estado !== EstadoPiscinaReserva::Pendiente) {
            return response()->json([
                'message' => 'Solo se pueden cancelar reservas pendientes',
            ], 422);
        }

        $validated = $request->validate([
            'motivo' => ['required', 'string'],
        ]);

        try {
            DB::transaction(function () use ($reserva, $validated) {
                // Actualizar estado
                $reserva->update([
                    'estado' => EstadoPiscinaReserva::Cancelada,
                    'motivo_rechazo' => $validated['motivo'],
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
            });

            return response()->json([
                'message' => 'Reserva cancelada exitosamente',
                'data' => $reserva->load('detalles'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cancelar reserva',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
