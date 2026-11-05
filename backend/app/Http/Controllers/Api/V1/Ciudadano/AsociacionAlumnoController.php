<?php

namespace App\Http\Controllers\Api\V1\Ciudadano;

use App\Http\Controllers\Controller;
use App\Models\AsociacionAlumno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fase 3 paso 1: catalogo de alumnos del presidente (web-public, auth:sanctum).
 *
 * Invariante de seguridad (espejo de CiudadanoAuthController): asociacion_id
 * SIEMPRE se deriva del token logueado, NUNCA del payload. Un presidente jamas
 * puede crear/mover/listar alumnos de otra asociacion. Sin destroy: desactivar
 * via /estado pa' no cascade-borrar la puente historica.
 */
class AsociacionAlumnoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $aid = $this->asociacionDelToken($request);
        if ($aid === null) {
            return $this->prohibido();
        }

        $alumnos = AsociacionAlumno::where('asociacion_id', $aid)
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn (AsociacionAlumno $a) => $this->serializar($a));

        return response()->json(['data' => $alumnos]);
    }

    public function store(Request $request): JsonResponse
    {
        $aid = $this->asociacionDelToken($request);
        if ($aid === null) {
            return $this->prohibido();
        }

        $v = $request->validate([
            'ci' => [
                'required', 'string', 'max:20', 'regex:/^[0-9]+$/',
                Rule::unique('asociacion_alumnos', 'ci')->where('asociacion_id', $aid),
            ],
            'nombre_completo' => ['required', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        // asociacion_id por propiedad, NO mass-assignment (fuera de fillable).
        $alumno = new AsociacionAlumno();
        $alumno->asociacion_id = $aid;
        $alumno->ci = $v['ci'];
        $alumno->nombre_completo = $v['nombre_completo'];
        $alumno->fecha_nacimiento = $v['fecha_nacimiento'] ?? null;
        $alumno->save();
        $alumno->refresh();

        return response()->json(['data' => $this->serializar($alumno)], 201);
    }

    public function update(Request $request, string $alumno): JsonResponse
    {
        $aid = $this->asociacionDelToken($request);
        if ($aid === null) {
            return $this->prohibido();
        }

        // Scoped por asociacion_id: findOrFail dentro de MI asociacion -> si el
        // id es de otra, 404 (no leakage de existencia), sin cargar la fila ajena.
        $modelo = AsociacionAlumno::where('asociacion_id', $aid)->findOrFail($alumno);

        $v = $request->validate([
            'ci' => [
                'required', 'string', 'max:20', 'regex:/^[0-9]+$/',
                Rule::unique('asociacion_alumnos', 'ci')->where('asociacion_id', $aid)->ignore($modelo->id),
            ],
            'nombre_completo' => ['required', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        // asociacion_id intocable: no esta en fillable y no se toca aca.
        $modelo->ci = $v['ci'];
        $modelo->nombre_completo = $v['nombre_completo'];
        $modelo->fecha_nacimiento = $v['fecha_nacimiento'] ?? null;
        $modelo->save();

        return response()->json(['data' => $this->serializar($modelo)]);
    }

    public function cambiarEstado(Request $request, string $alumno): JsonResponse
    {
        $aid = $this->asociacionDelToken($request);
        if ($aid === null) {
            return $this->prohibido();
        }

        $modelo = AsociacionAlumno::where('asociacion_id', $aid)->findOrFail($alumno);
        $request->validate(['activo' => 'required|boolean']);
        $modelo->activo = (bool) $request->input('activo');
        $modelo->save();

        return response()->json(['data' => $this->serializar($modelo)]);
    }

    /** Devuelve el asociacion_id del presidente logueado, o null si no es asociacion. */
    private function asociacionDelToken(Request $request): ?string
    {
        $ciudadano = $request->user();
        if (! $ciudadano || ! $ciudadano->es_asociacion || blank($ciudadano->asociacion_id)) {
            return null;
        }
        return $ciudadano->asociacion_id;
    }

    private function prohibido(): JsonResponse
    {
        return response()->json([
            'error' => 'NO_ES_ASOCIACION',
            'mensaje' => 'Solo un presidente de asociacion puede gestionar alumnos.',
        ], 403);
    }

    private function serializar(AsociacionAlumno $a): array
    {
        return [
            'id' => $a->id,
            'ci' => $a->ci,
            'nombre_completo' => $a->nombre_completo,
            'fecha_nacimiento' => $a->fecha_nacimiento?->toDateString(),
            'activo' => $a->activo,
        ];
    }
}
