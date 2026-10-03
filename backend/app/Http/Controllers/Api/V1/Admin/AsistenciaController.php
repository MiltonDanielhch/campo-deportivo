<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAsistenciaRequest;
use App\Models\Reserva;
use App\Services\MarcarAsistenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function __construct(
        private MarcarAsistenciaService $asistencia,
    ) {
    }

    /**
     * POST /v1/admin/reservas/{id}/asistencia
     *
     * Body:
     * {
     *   "marcar": true|false
     * }
     */
    public function store(
        StoreAsistenciaRequest $request,
        string $id
    ): JsonResponse {
        $reserva = Reserva::with([
            'solicitud',
            'campo',
            'detalle',
        ])->find($id);

        if (! $reserva) {
            abort(404);
        }

        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        $reserva = $this->asistencia->ejecutar(
            $reserva,
            $funcionario,
            $request->boolean('marcar')
        );

        return response()->json([
            'data' => [
                'id' => $reserva->id,
                'codigo_reserva' => $reserva->codigo_reserva,
                'solicitud_reserva_id' => $reserva->solicitud_reserva_id,
                'campo_id' => $reserva->campo_id,
                'fecha_reserva' => $reserva->fecha_reserva?->toDateString(),
                'hora_inicio' => $reserva->hora_inicio,
                'hora_fin' => $reserva->hora_fin,
                'asistencia_marcada_en' => $reserva->asistencia_marcada_en?->toIso8601String(),
                'asistencia_marcada_por' => $reserva->asistencia_marcada_por,
            ],
        ]);
    }
}
