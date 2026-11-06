<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAsistenciaRequest;
use App\Models\Reserva;
use App\Models\SolicitudDetalleAlumno;
use App\Services\MarcarAsistenciaAlumnoService;
use App\Services\MarcarAsistenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function __construct(
        private MarcarAsistenciaService $asistencia,
        private MarcarAsistenciaAlumnoService $asistenciaAlumno,
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

    /**
     * POST /v1/admin/solicitud-detalle-alumno/{id}/asistencia
     *
     * Body: { "marcar": true|false }
     *
     * {id} es el uuid de la bridge (alumno asignado a una franja). Si el
     * alumno no está en esa franja, no hay bridge → 404 (no se marca
     * asistencia de un invitado).
     */
    public function marcarAlumno(
        StoreAsistenciaRequest $request,
        string $id
    ): JsonResponse {
        $bridge = SolicitudDetalleAlumno::with([
            'detalle.solicitud',
            'detalle.campo',
            'alumno',
        ])->find($id);

        if (! $bridge) {
            abort(404);
        }

        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        $bridge = $this->asistenciaAlumno->ejecutar(
            $bridge,
            $funcionario,
            $request->boolean('marcar')
        );

        return response()->json([
            'data' => [
                'id' => $bridge->id,
                'detalle_id' => $bridge->detalle_id,
                'alumno_id' => $bridge->alumno_id,
                'asistencia_marca_en' => $bridge->asistencia_marca_en?->toIso8601String(),
                'asistencia_marca_por' => $bridge->asistencia_marca_por,
            ],
        ]);
    }
}
