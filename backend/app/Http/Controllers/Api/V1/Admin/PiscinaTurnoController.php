<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampoDeportivo;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Services\Piscina\PiscinaTurnoLoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Controller admin para gestión de turnos de piscina.
 */
class PiscinaTurnoController extends Controller
{
    public function __construct(
        private PiscinaTurnoLoteService $lote,
    ) {
    }

    /**
     * GET /api/v1/admin/piscina/campos/{campoId}/turnos
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

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/v1/admin/piscina/turnos
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campo_id' => ['required', 'uuid', 'exists:campos_deportivos,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i:s', 'after:hora_inicio'],
            'cupo_total' => ['nullable', 'integer', 'min:1'],
            // ⟦FIX 1⟧ era 'in:activo,activo' (typo) → nunca se podía inactivar.
            'estado' => ['required', 'in:activo,inactivo'],
            'observacion' => ['nullable', 'string'],
            'perfiles' => ['required', 'array', 'min:1'],
            'perfiles.*.perfil' => ['required', 'in:mayor_12,menor_12,institucion'],
            'perfiles.*.cupo' => ['required', 'integer', 'min:1'],
            'perfiles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'perfiles.*.tarifa_sireb_id' => ['nullable', 'uuid'],
        ]);

        $campo = CampoDeportivo::find($validated['campo_id']);
        if ($campo->modalidad_reserva?->value !== 'turno_por_perfil') {
            return response()->json([
                'message' => 'Este campo no está configurado como piscina',
            ], 422);
        }

        // ⟦Gate#1 alineado con cancha⟧ piscina no tiene EXCLUDE de rango en BD,
        // así que el solape se corta acá en PHP (mismo criterio que el lote).
        if ($this->lote->haySolapeEnFecha($validated['campo_id'], $validated['fecha'], $validated['hora_inicio'], $validated['hora_fin'])) {
            return response()->json([
                'message' => 'Ya existe un turno activo que se solapa con ese horario en esa fecha.',
            ], 422);
        }

        try {
            $turno = DB::transaction(function () use ($validated) {
                $turno = PiscinaTurno::create([
                    'campo_id' => $validated['campo_id'],
                    'fecha' => $validated['fecha'],
                    'hora_inicio' => $validated['hora_inicio'],
                    'hora_fin' => $validated['hora_fin'],
                    'cupo_total' => $validated['cupo_total'],
                    'estado' => $validated['estado'],
                    'observacion' => $validated['observacion'],
                ]);

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

            return response()->json(['message' => 'Turno creado exitosamente', 'data' => $turno], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear turno', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/admin/piscina/turnos/lote
     *
     * Clona los turnos activos de un día referencia a un rango de fechas.
     */
    public function storeLote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campo_id' => ['required', 'uuid', 'exists:campos_deportivos,id'],
            'fecha_origen' => ['required', 'date'],
            'desde' => ['required', 'date', 'after_or_equal:today'],
            'hasta' => [
                'required', 'date', 'after_or_equal:desde',
                'before_or_equal:' . now()->addDays(60)->toDateString(),
            ],
        ]);

        $campo = CampoDeportivo::find($validated['campo_id']);
        if ($campo->modalidad_reserva?->value !== 'turno_por_perfil') {
            return response()->json(['message' => 'Este campo no está configurado como piscina'], 422);
        }

        try {
            $res = $this->lote->clonarDesdeReferencia(
                $validated['campo_id'],
                $validated['fecha_origen'],
                $validated['desde'],
                $validated['hasta'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar el lote', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => "Lote procesado: {$res['creados']} turnos creados, " . count($res['omitidas']) . " fechas omitidas por solape.",
            'data' => $res,
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/piscina/turnos/{turnoId}
     * (el intervalo fecha/hora es inmutable acá → no genera solape nuevo;
     *  el reservado=0 al recrear perfiles sigue siendo Fix 3, gate PO)
     */
    public function update(Request $request, string $turnoId): JsonResponse
    {
        $turno = PiscinaTurno::find($turnoId);
        if (!$turno) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        $validated = $request->validate([
            'estado' => ['sometimes', 'in:activo,inactivo'], // ⟦FIX 1⟧
            'observacion' => ['nullable', 'string'],
            'perfiles' => ['sometimes', 'array'],
            'perfiles.*.perfil' => ['required', 'in:mayor_12,menor_12,institucion'],
            'perfiles.*.cupo' => ['required', 'integer', 'min:1'],
            'perfiles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'perfiles.*.tarifa_sireb_id' => ['nullable', 'uuid'],
        ]);

        try {
            DB::transaction(function () use ($turno, $validated) {
                if (isset($validated['estado'])) {
                    $turno->estado = $validated['estado'];
                }
                if (isset($validated['observacion'])) {
                    $turno->observacion = $validated['observacion'];
                }
                $turno->save();

                if (isset($validated['perfiles'])) {
                    $turno->perfiles()->delete();
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
                }
            });

            return response()->json(['message' => 'Turno actualizado exitosamente', 'data' => $turno->load('perfiles')]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar turno', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/piscina/turnos/{turnoId}
     */
    public function destroy(string $turnoId): JsonResponse
    {
        $turno = PiscinaTurno::find($turnoId);
        if (!$turno) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        // ⟦FIX 4b⟧ el detalle NO tiene columna 'estado'; se filtra por cabecera.
        $hayPendientes = $turno->reservas()
            ->whereHas('reserva', fn ($q) => $q->where('estado', 'pendiente'))
            ->exists();

        if ($hayPendientes) {
            return response()->json([
                'message' => 'No se puede eliminar: el turno tiene reservas pendientes',
            ], 422);
        }

        try {
            $turno->delete();
            return response()->json(['message' => 'Turno eliminado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar turno', 'error' => $e->getMessage()], 500);
        }
    }
}
