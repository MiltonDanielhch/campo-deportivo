<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampoDeportivo;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller admin para gestión de turnos de piscina.
 */
class PiscinaTurnoController extends Controller
{
    /**
     * GET /api/v1/admin/piscina/campos/{campoId}/turnos
     *
     * Lista turnos de un campo piscina.
     */
    public function index(Request $request, string $campoId): JsonResponse
    {
        $campo = CampoDeportivo::find($campoId);
        if (!$campo) {
            return response()->json(['message' => 'Campo no encontrado'], 404);
        }

        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $query = PiscinaTurno::where('campo_id', $campoId)
            ->with('perfiles')
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($fechaDesde) {
            $query->where('fecha', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->where('fecha', '<=', $fechaHasta);
        }

        $turnos = $query->get();

        return response()->json([
            'data' => $turnos,
        ]);
    }

    /**
     * POST /api/v1/admin/piscina/turnos
     *
     * Crea un nuevo turno de piscina.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campo_id' => ['required', 'uuid', 'exists:campos_deportivos,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i:s', 'after:hora_inicio'],
            'cupo_total' => ['nullable', 'integer', 'min:1'],
            'estado' => ['required', 'in:activo,activo'],
            'observacion' => ['nullable', 'string'],
            'perfiles' => ['required', 'array', 'min:1'],
            'perfiles.*.perfil' => ['required', 'in:mayor_12,menor_12,institucion'],
            'perfiles.*.cupo' => ['required', 'integer', 'min:1'],
            'perfiles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'perfiles.*.tarifa_sireb_id' => ['nullable', 'uuid'],
        ]);

        // Validar que el campo sea piscina
        $campo = CampoDeportivo::find($validated['campo_id']);
        if ($campo->modalidad_reserva?->value !== 'turno_por_perfil') {
            return response()->json([
                'message' => 'Este campo no está configurado como piscina',
            ], 422);
        }

        try {
            $turno = DB::transaction(function () use ($validated) {
                // Crear turno
                $turno = PiscinaTurno::create([
                    'campo_id' => $validated['campo_id'],
                    'fecha' => $validated['fecha'],
                    'hora_inicio' => $validated['hora_inicio'],
                    'hora_fin' => $validated['hora_fin'],
                    'cupo_total' => $validated['cupo_total'],
                    'estado' => $validated['estado'],
                    'observacion' => $validated['observacion'],
                ]);

                // Crear perfiles del turno
                foreach ($validated['perfiles'] as $perfilData) {
                    $turno->perfiles()->create([
                        'perfil' => $perfilData['perfil'],
                        'cupo' => $perfilData['cupo'],
                        'reservado' => 0,
                        'tarifa_sireb_id' => $perfilData['tarifa_sireb_id'] ?? null,
                        'precio_unitario' => $perfilData['precio_unitario'],
                        'estado' => 'activo',
                    ]);
                }

                return $turno->load('perfiles');
            });

            return response()->json([
                'message' => 'Turno creado exitosamente',
                'data' => $turno,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear turno',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/v1/admin/piscina/turnos/{turnoId}
     *
     * Actualiza un turno de piscina.
     */
    public function update(Request $request, string $turnoId): JsonResponse
    {
        $turno = PiscinaTurno::find($turnoId);
        if (!$turno) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        $validated = $request->validate([
            'estado' => ['sometimes', 'in:activo,activo'],
            'observacion' => ['nullable', 'string'],
            'perfiles' => ['sometimes', 'array'],
            'perfiles.*.perfil' => ['required', 'in:mayor_12,menor_12,institucion'],
            'perfiles.*.cupo' => ['required', 'integer', 'min:1'],
            'perfiles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'perfiles.*.tarifa_sireb_id' => ['nullable', 'uuid'],
        ]);

        try {
            DB::transaction(function () use ($turno, $validated) {
                // Actualizar turno
                if (isset($validated['estado'])) {
                    $turno->estado = $validated['estado'];
                }
                if (isset($validated['observacion'])) {
                    $turno->observacion = $validated['observacion'];
                }
                $turno->save();

                // Actualizar perfiles si se proporcionan
                if (isset($validated['perfiles'])) {
                    // Eliminar perfiles existentes
                    $turno->perfiles()->delete();

                    // Crear nuevos perfiles
                    foreach ($validated['perfiles'] as $perfilData) {
                        $turno->perfiles()->create([
                            'perfil' => $perfilData['perfil'],
                            'cupo' => $perfilData['cupo'],
                            'reservado' => 0, // Resetear reservado al actualizar
                            'tarifa_sireb_id' => $perfilData['tarifa_sireb_id'] ?? null,
                            'precio_unitario' => $perfilData['precio_unitario'],
                            'estado' => 'activo',
                        ]);
                    }
                }
            });

            return response()->json([
                'message' => 'Turno actualizado exitosamente',
                'data' => $turno->load('perfiles'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar turno',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/piscina/turnos/{turnoId}
     *
     * Elimina un turno de piscina.
     */
    public function destroy(string $turnoId): JsonResponse
    {
        $turno = PiscinaTurno::find($turnoId);
        if (!$turno) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        // Verificar que no tenga reservas activas
        if ($turno->reservas()->where('estado', 'pendiente')->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el turno tiene reservas pendientes',
            ], 422);
        }

        try {
            $turno->delete();

            return response()->json([
                'message' => 'Turno eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar turno',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
