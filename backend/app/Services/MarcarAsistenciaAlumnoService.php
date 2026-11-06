<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\Funcionario;
use App\Models\SolicitudDetalleAlumno;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Marca o desmarca asistencia de UN ALUMNO en una franja (bridge
 * solicitud_detalle_alumno). Espejo de MarcarAsistenciaService (franja),
 * pero la raíz es la bridge->detalle en vez de la reserva: no se puede
 * reutilizar el service de franja porque está acoplado a Reserva.
 *
 * Reglas (idénticas a franja):
 * - Solo alumnos de solicitudes CONFIRMADAS.
 * - Admin marca/desmarca sin restricción de ventana.
 * - funcionario_control solo si está asignado al campo y dentro de ventana.
 * - Ventana: 1 hora antes del inicio del DETALLE hasta 24 h después del fin.
 * - Cada acción queda auditada.
 */
class MarcarAsistenciaAlumnoService
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {
    }

    public function ejecutar(
        SolicitudDetalleAlumno $bridge,
        Funcionario $funcionario,
        bool $marcar,
    ): SolicitudDetalleAlumno {
        $bridge->loadMissing([
            'detalle.solicitud',
            'detalle.campo.asignacionesFuncionario',
        ]);

        $detalle = $bridge->detalle;

        if (! $detalle || ! $detalle->solicitud) {
            throw ValidationException::withMessages([
                'alumno' => 'El alumno asignado no tiene franja/solicitud asociada.',
            ]);
        }

        if ($detalle->solicitud->estado !== EstadoSolicitudReserva::Confirmada) {
            throw ValidationException::withMessages([
                'alumno' => 'Solo se puede marcar asistencia en solicitudes confirmadas.',
            ]);
        }

        $esAdmin = $this->esAdministrador($funcionario);

        if (! $esAdmin) {
            if (! $detalle->campo) {
                throw ValidationException::withMessages([
                    'alumno' => 'La franja no tiene campo asociado.',
                ]);
            }

            $asignado = $detalle->campo
                ->asignacionesFuncionario
                ->contains('funcionario_id', $funcionario->id);

            if (! $asignado) {
                throw new AccessDeniedHttpException(
                    'No estás asignado al campo de esta franja.'
                );
            }

            if (! $this->dentroDeVentana($detalle)) {
                throw ValidationException::withMessages([
                    'alumno' => 'Fuera de ventana temporal. Solo un administrador puede marcar asistencia fuera de este rango.',
                ]);
            }
        }

        $antes = $this->snapshot($bridge);

        DB::transaction(function () use ($bridge, $funcionario, $marcar, $antes) {
            if ($marcar) {
                // Idempotencia en estado final: si ya está marcada, no se pisa
                // el timestamp original (espejo del service de franja).
                if ($bridge->asistencia_marca_en === null) {
                    $bridge->forceFill([
                        'asistencia_marca_en' => now(),
                        'asistencia_marca_por' => $funcionario->id,
                    ])->save();
                }
            } else {
                if ($bridge->asistencia_marca_en !== null) {
                    $bridge->forceFill([
                        'asistencia_marca_en' => null,
                        'asistencia_marca_por' => null,
                    ])->save();
                }
            }

            $bridge->refresh();

            $despues = $this->snapshot($bridge);

            $this->auditoria->registrar(
                'solicitud_detalle_alumno',
                $bridge->id,
                $marcar ? 'marcar_asistencia_alumno' : 'desmarcar_asistencia_alumno',
                $funcionario->id,
                $antes,
                $despues,
            );
        });

        return $bridge->refresh();
    }

    private function esAdministrador(Funcionario $funcionario): bool
    {
        return $funcionario->tienePermiso('*')
            || in_array(
                $funcionario->rol?->nombre,
                ['admin_parametricas', 'admin_reservas'],
                true
            );
    }

    /**
     * Espejo de MarcarAsistenciaService::dentroDeVentana, derivada del DETALLE
     * (fecha_reserva/hora_inicio/hora_fin) en vez de la reserva. Misma ventana:
     * 1 h antes del inicio hasta 24 h después del fin.
     */
    private function dentroDeVentana($detalle): bool
    {
        $fecha = $detalle->fecha_reserva instanceof \DateTimeInterface
            ? $detalle->fecha_reserva->format('Y-m-d')
            : (string) $detalle->fecha_reserva;

        $inicio = Carbon::parse($fecha.' '.$detalle->hora_inicio);
        $fin = Carbon::parse($fecha.' '.$detalle->hora_fin);

        $ventanaInicio = $inicio->copy()->subHour();
        $ventanaFin = $fin->copy()->addDay();

        return now()->between($ventanaInicio, $ventanaFin, true);
    }

    private function snapshot(SolicitudDetalleAlumno $bridge): array
    {
        return [
            'asistencia_marca_en' => $bridge->asistencia_marca_en?->toIso8601String(),
            'asistencia_marca_por' => $bridge->asistencia_marca_por,
        ];
    }
}
