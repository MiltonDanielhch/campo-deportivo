<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\DTOs\PiscinaReservaDTO;
use App\Exceptions\FranjaNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Piscina\StorePiscinaReservaRequest;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\PiscinaReserva;
use App\Services\Piscina\PiscinaConfirmacionCobroService;
use App\Services\Piscina\PiscinaDisponibilidadService;
use App\Services\Piscina\PiscinaReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller público para el módulo de piscina.
 */
class PiscinaController extends Controller
{
    public function __construct(
        private PiscinaDisponibilidadService $disponibilidad,
        private PiscinaReservaService $reservaService,
        private RecaudacionesApiClientInterface $sirebClient,
        private PiscinaConfirmacionCobroService $confirmacionCobro,
    ) {
    }

    /**
     * GET /api/v1/public/piscina/campos/{campoId}/turnos?fecha=2026-10-16
     *
     * Obtiene turnos disponibles para un campo piscina en una fecha.
     */
    public function turnos(Request $request, string $campoId): JsonResponse
    {
        $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $fecha = $request->input('fecha');

        try {
            $disponibilidad = $this->disponibilidad->obtenerTurnosDisponibles($campoId, $fecha);

            return response()->json([
                'data' => $disponibilidad,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener disponibilidad',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/public/piscina/reservas
     *
     * Crea una nueva reserva de piscina.
     */
    public function reservar(StorePiscinaReservaRequest $request): JsonResponse
    {
        try {
            $dto = new PiscinaReservaDTO(
                fecha: $request->input('fecha'),
                turnoId: $request->input('turno_id'),
                items: $request->input('items'),
                nombrePagador: $request->input('nombre_pagador'),
                telefonoPagador: $request->input('telefono_pagador'),
                ciNitPagador: $request->input('ci_nit_pagador'),
                ipCobrador: $request->ip(),
            );

            $resultado = $this->reservaService->crear($dto);

            return response()->json([
                'message' => 'Reserva creada exitosamente',
                'data' => $resultado,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (FranjaNoDisponibleException $e) {
            // El cupo se agotó por concurrencia: 409 para que la app lo
            // distinga de un error de validación (422) o de servidor (500).
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'franja_no_disponible',
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear reserva',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/public/piscina/reservas/{codigo}/estado
     *
     * Obtiene el estado de una reserva de piscina.
     *
     * Si la reserva está pendiente y tiene referencia_recaudaciones, consulta
     * SIREB y, si ya fue pagada allá, confirma la reserva localmente (idempotente).
     * Mismo patrón que SolicitudEstadoController para que el polling del frontend
     * (Pago.tsx) detecte la confirmación cuando el usuario paga en SIREB.
     */
    public function estado(string $codigo): JsonResponse
    {
        $reserva = PiscinaReserva::where('codigo_seguimiento', $codigo)
            ->with(['detalles.turno', 'campo'])
            ->first();

        if (! $reserva) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }

        if ($reserva->estado->value === 'pendiente' && $reserva->referencia_recaudaciones) {
            try {
                $estadoSireb = $this->sirebClient->consultarLiquidacionPorCodigo(
                    $reserva->referencia_recaudaciones
                );

                if ($estadoSireb && ($estadoSireb['estado'] ?? '') === 'pagada') {
                    $montoConfirmado = $estadoSireb['monto'] ?? null;
                    $this->confirmacionCobro->confirmar($reserva, $montoConfirmado);
                    $reserva->refresh();
                }
            } catch (\Exception $e) {
                Log::channel('sireb')->warning('Error al verificar estado SIREB en consulta pública de piscina', [
                    'codigo_seguimiento' => $codigo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => $reserva,
        ]);
    }
}
