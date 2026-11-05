<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TipoAsociacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAsociacionRequest;
use App\Http\Requests\Admin\UpdateAsociacionRequest;
use App\Http\Resources\AsociacionResource;
use App\Models\Asociacion;
use Illuminate\Http\Request;

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
}
