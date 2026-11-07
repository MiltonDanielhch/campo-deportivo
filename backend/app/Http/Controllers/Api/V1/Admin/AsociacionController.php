<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TipoAsociacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAsociacionRequest;
use App\Http\Requests\Admin\UpdateAsociacionRequest;
use App\Http\Resources\AsociacionResource;
use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AsociacionController extends Controller
{
    public function index(Request $r)
    {
        $q = Asociacion::query()->orderBy('nombre');
        if ($r->filled('buscar')) {
            $t = '%'.$r->input('buscar').'%';
            $q->where(fn ($x) => $x->where('nombre','ILIKE',$t)->orWhere('nit','ILIKE',$t)
                ->orWhere('responsable_ci','ILIKE',$t)->orWhere('responsable_nombre','ILIKE',$t));
        }
        if ($r->filled('tipo')) $q->where('tipo', $r->input('tipo'));
        return AsociacionResource::collection($q->paginate($r->input('per_page', 15)));
    }

    public function store(StoreAsociacionRequest $r)
    {
        $a = Asociacion::create($r->validated());
        return (new AsociacionResource($a))->response()->setStatusCode(201);
    }

    public function show(Asociacion $asociacion) { return new AsociacionResource($asociacion); }

    public function update(UpdateAsociacionRequest $r, Asociacion $asociacion)
    {
        $asociacion->update($r->validated());
        return new AsociacionResource($asociacion);
    }

    public function cambiarEstado(Request $r, Asociacion $asociacion)
    {
        $r->validate(['activo' => 'required|boolean']);
        $asociacion->update(['activo' => (bool) $r->input('activo')]);
        return new AsociacionResource($asociacion);
    }

    /** Pa' la ventanilla: siempre 200, asociacion|null (el dialog no trata "no es" como error). */
    public function porResponsable(string $ci)
    {
        $a = Asociacion::where('responsable_ci', $ci)->where('activo', true)->first();
        return response()->json(['asociacion' => $a ? new AsociacionResource($a) : null]);
    }

    /**
     * [Fase 3] Alumnos ACTIVOS de una asociacion, pa' el multi-select de
     * ventanilla. Va bajo role:admin_parametricas|admin_reservas (mismo grupo
     * que porResponsable), no abierto a cualquier funcionario: son datos de
     * terceros (potencialmente menores).
     */
    public function alumnos(Asociacion $asociacion)
    {
        $alumnos = $asociacion->alumnos()
            ->where('activo', true)
            ->orderBy('nombre_completo')
            ->get(['id', 'ci', 'nombre_completo']);

        return response()->json(['data' => $alumnos]);
    }

    // ─── Fase 5: admin-fallback de alumnos (respaldo del presidente) ───
    // Espejo de AsociacionAlumnoController (ciudadano) con UNA diferencia de
    // fuente: asociacion_id sale del ROUTE PARAM validado por model binding
    // ($asociacion->id), no del token. Mismas defensas: asociacion_id fuera
    // de fillable (set por propiedad), scope where(asociacion_id)->findOrFail
    // (404 sin leakage de otra asociacion), CI unico por asociacion, sin
    // destroy (desactivar via /estado). NO filtra vigencia de la asociacion:
    // el GAD prepara la proxima temporada antes de reactivar; cargar alumnos
    // en una inactiva no da convenio (el descuento se valida al reservar via
    // AsociacionVigenteResolver, no aqui).

    /** Read de gestion: TODOS los alumnos (activos e inactivos) con fecha. */
    public function alumnosGestion(Asociacion $asociacion): JsonResponse
    {
        $alumnos = $asociacion->alumnos()
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn (AsociacionAlumno $a) => $this->serializarAlumno($a));

        return response()->json(['data' => $alumnos]);
    }

    public function storeAlumno(Request $r, Asociacion $asociacion): JsonResponse
    {
        $aid = $asociacion->id;

        $v = $r->validate([
            'ci' => [
                'required', 'string', 'max:20', 'regex:/^[0-9]+$/',
                Rule::unique('asociacion_alumnos', 'ci')->where('asociacion_id', $aid),
            ],
            'nombre_completo' => ['required', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $alumno = new AsociacionAlumno();
        $alumno->asociacion_id = $aid;
        $alumno->ci = $v['ci'];
        $alumno->nombre_completo = $v['nombre_completo'];
        $alumno->fecha_nacimiento = $v['fecha_nacimiento'] ?? null;
        $alumno->save();
        $alumno->refresh();

        return response()->json(['data' => $this->serializarAlumno($alumno)], 201);
    }

    public function updateAlumno(Request $r, Asociacion $asociacion, string $alumno): JsonResponse
    {
        $aid = $asociacion->id;

        // Scoped: si el id es de otra asociacion, 404 sin cargar la fila ajena.
        $modelo = AsociacionAlumno::where('asociacion_id', $aid)->findOrFail($alumno);

        $v = $r->validate([
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

        return response()->json(['data' => $this->serializarAlumno($modelo)]);
    }

    public function cambiarEstadoAlumno(Request $r, Asociacion $asociacion, string $alumno): JsonResponse
    {
        $aid = $asociacion->id;
        $modelo = AsociacionAlumno::where('asociacion_id', $aid)->findOrFail($alumno);

        $r->validate(['activo' => 'required|boolean']);
        $modelo->activo = (bool) $r->input('activo');
        $modelo->save();

        return response()->json(['data' => $this->serializarAlumno($modelo)]);
    }

    /** Mismo shape que el ciudadano (incluye fecha_nacimiento pa' editar). */
    private function serializarAlumno(AsociacionAlumno $a): array
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
