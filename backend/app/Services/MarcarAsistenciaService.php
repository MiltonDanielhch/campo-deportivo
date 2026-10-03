<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\Funcionario;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MarcarAsistenciaService
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {
    }

    /**
     * Marca o desmarca asistencia de una reserva.
     *
     * Reglas:
     * - Solo reservas de solicitudes confirmadas.
     * - Admin puede marcar/desmarcar sin restricción de ventana.
     * - funcionario_control solo si está asignado al campo y dentro de ventana.
     * - Ventana: desde 1 hora antes del inicio hasta 24 horas después del fin.
     * - Cada acción queda auditada.
     */
    public function ejecutar(
        Reserva $reserva,
        Funcionario $funcionario,
        bool $marcar
    ): Reserva {
        $reserva->loadMissing([
            'solicitud',
            'campo.asignacionesFuncionario',
        ]);

        if (! $reserva->solicitud) {
            throw ValidationException::withMessages([
                'reserva' => 'La reserva no tiene solicitud asociada.',
            ]);
        }

        if ($reserva->solicitud->estado !== EstadoSolicitudReserva::Confirmada) {
            throw ValidationException::withMessages([
                'reserva' => 'Solo se puede marcar asistencia en reservas de solicitudes confirmadas.',
            ]);
        }

        $esAdmin = $this->esAdministrador($funcionario);

        if (! $esAdmin) {
            if (! $reserva->campo) {
                throw ValidationException::withMessages([
                    'reserva' => 'La reserva no tiene campo asociado.',
                ]);
            }

            $asignado = $reserva->campo
                ->asignacionesFuncionario
                ->contains('funcionario_id', $funcionario->id);

            if (! $asignado) {
                throw new AccessDeniedHttpException(
                    'No estás asignado al campo de esta reserva.'
                );
            }

            if (! $this->dentroDeVentana($reserva)) {
                throw ValidationException::withMessages([
                    'reserva' => 'Fuera de ventana temporal. Solo un administrador puede marcar asistencia fuera de este rango.',
                ]);
            }
        }

        $antes = $this->snapshot($reserva);

        DB::transaction(function () use ($reserva, $funcionario, $marcar, $antes) {
            if ($marcar) {
                // Idempotencia en estado final:
                // si ya está marcada, no se pisa el timestamp original.
                if ($reserva->asistencia_marcada_en === null) {
                    $reserva->forceFill([
                        'asistencia_marcada_en' => now(),
                        'asistencia_marcada_por' => $funcionario->id,
                    ])->save();
                }
            } else {
                if ($reserva->asistencia_marcada_en !== null) {
                    $reserva->forceFill([
                        'asistencia_marcada_en' => null,
                        'asistencia_marcada_por' => null,
                    ])->save();
                }
            }

            $reserva->refresh();

            $despues = $this->snapshot($reserva);

            $this->auditoria->registrar(
                'reservas',
                $reserva->id,
                $marcar ? 'marcar_asistencia' : 'desmarcar_asistencia',
                $funcionario->id,
                $antes,
                $despues,
            );
        });

        return $reserva->refresh();
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

    private function dentroDeVentana(Reserva $reserva): bool
    {
        $fecha = $reserva->fecha_reserva instanceof \DateTimeInterface
            ? $reserva->fecha_reserva->format('Y-m-d')
            : (string) $reserva->fecha_reserva;

        $inicio = Carbon::parse($fecha.' '.$reserva->hora_inicio);
        $fin = Carbon::parse($fecha.' '.$reserva->hora_fin);

        $ventanaInicio = $inicio->copy()->subHour();
        $ventanaFin = $fin->copy()->addDay();

        return now()->between($ventanaInicio, $ventanaFin, true);
    }

    private function snapshot(Reserva $reserva): array
    {
        return [
            'asistencia_marcada_en' => $reserva->asistencia_marcada_en?->toIso8601String(),
            'asistencia_marcada_por' => $reserva->asistencia_marcada_por,
        ];
    }
}
