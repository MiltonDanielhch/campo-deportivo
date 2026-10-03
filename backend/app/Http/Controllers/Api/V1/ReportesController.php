<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportesService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de reportes gerenciales (HU-F2 y HU-F3).
 *
 * Endpoints:
 * - GET /api/v1/reportes/ingresos?desde=...&hasta=...&campo_id=...
 * - GET /api/v1/reportes/horas-pico?desde=...&hasta=...
 * - GET /api/v1/reportes/clientes-frecuentes?desde=...&hasta=...
 *
 * Solo accesible para admin_parametricas y gerencia.
 */
class ReportesController extends Controller
{
    public function __construct(
        private ReportesService $reportes,
    ) {
    }

    /**
     * GET /api/v1/reportes/ingresos
     *
     * Suma de reservas.monto_pagado agrupado por campo.
     */
    public function ingresos(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->validarRangoFechas($request);

        $campoId = $request->query('campo_id');

        $datos = $this->reportes->ingresosPorCampo($desde, $hasta, $campoId);

        $totalGeneral = array_sum(array_column($datos, 'total_ingresos'));

        return response()->json([
            'data' => $datos,
            'meta' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'total_general' => $totalGeneral,
                'nota' => ReportesService::NOTA_VISTA_OPERATIVA,
            ],
        ]);
    }

    /**
     * GET /api/v1/reportes/horas-pico
     *
     * Conteo de reservas agrupado por la hora de inicio.
     */
    public function horasPico(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->validarRangoFechas($request);

        $datos = $this->reportes->histogramaHorasPico($desde, $hasta);

        // Calcular la hora pico (la con más reservas)
        $horaPico = collect($datos)->sortByDesc('total_reservas')->first();

        return response()->json([
            'data' => $datos,
            'meta' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'hora_pico' => $horaPico['total_reservas'] > 0 ? $horaPico['hora'] : null,
                'total_reservas' => array_sum(array_column($datos, 'total_reservas')),
                'nota' => ReportesService::NOTA_VISTA_OPERATIVA,
            ],
        ]);
    }

    /**
     * GET /api/v1/reportes/clientes-frecuentes
     *
     * Agrupación por COALESCE(ci_nit_pagador, telefono_pagador).
     */
    public function clientesFrecuentes(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->validarRangoFechas($request);

        $limite = min(100, max(1, $request->integer('limite', 50)));

        $datos = $this->reportes->clientesFrecuentes($desde, $hasta, $limite);

        return response()->json([
            'data' => $datos,
            'meta' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'total_clientes' => count($datos),
                'nota' => ReportesService::NOTA_VISTA_OPERATIVA,
            ],
        ]);
    }

    /**
     * Valida y parsea el rango de fechas desde/hasta.
     *
     * @return array{0: Carbon, 1: Carbon}
     * @throws ValidationException
     */
    private function validarRangoFechas(Request $request): array
    {
        $desdeStr = $request->query('desde');
        $hastaStr = $request->query('hasta');

        if (! $desdeStr || ! $hastaStr) {
            throw ValidationException::withMessages([
                'desde' => 'El parámetro "desde" es obligatorio.',
                'hasta' => 'El parámetro "hasta" es obligatorio.',
            ]);
        }

        try {
            $desde = Carbon::createFromFormat('Y-m-d', $desdeStr)->startOfDay();
            $hasta = Carbon::createFromFormat('Y-m-d', $hastaStr)->endOfDay();
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'desde' => 'Formato inválido. Use YYYY-MM-DD.',
                'hasta' => 'Formato inválido. Use YYYY-MM-DD.',
            ]);
        }

        if ($desde->gt($hasta)) {
            throw ValidationException::withMessages([
                'desde' => 'La fecha "desde" no puede ser posterior a "hasta".',
            ]);
        }

        return [$desde, $hasta];
    }
}
