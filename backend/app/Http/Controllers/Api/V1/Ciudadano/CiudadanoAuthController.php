<?php

namespace App\Http\Controllers\Api\V1\Ciudadano;

use App\Http\Controllers\Controller;
use App\Models\Asociacion;
use App\Models\Ciudadano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CiudadanoAuthController extends Controller
{
    public function registro(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ci' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/', 'unique:ciudadanos,ci'],
            'nombre_completo' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $asociacion = $this->resolverAsociacionVigentePorCi($validated['ci']);
        $vinculada = $asociacion !== null;

        $ciudadano = Ciudadano::create([
            'ci' => $validated['ci'],
            'nombre_completo' => $validated['nombre_completo'],
            'telefono' => $validated['telefono'] ?? null,
            'password_hash' => Hash::make($validated['password']),
        ]);

        if ($vinculada) {
            $ciudadano->es_asociacion = true;
            $ciudadano->asociacion_id = $asociacion->id;
            $ciudadano->save();
        }

        // refresh() trae los defaults de BD al modelo en memoria: sin esto
        // es_asociacion queda null (no false) en los caminos NO vinculados,
        // porque create() no seteo la columna (fuera de fillable a proposito).
        $ciudadano->refresh();

        $token = $ciudadano->createToken('ciudadano')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'ciudadano' => $this->serializar($ciudadano->loadMissing('asociacion')),
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ci' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $ciudadano = Ciudadano::where('ci', $validated['ci'])->first();

        if (! $ciudadano || ! Hash::check($validated['password'], $ciudadano->password_hash)) {
            return response()->json(['error' => 'CREDENCIALES_INVALIDAS'], 401);
        }

        $token = $ciudadano->createToken('ciudadano')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'ciudadano' => $this->serializar($ciudadano->loadMissing('asociacion')),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Ciudadano $ciudadano */
        $ciudadano = $request->user();

        return response()->json([
            'data' => $this->serializar($ciudadano->loadMissing('asociacion')),
        ]);
    }

    public function asociacion(Request $request): JsonResponse
    {
        /** @var Ciudadano $ciudadano */
        $ciudadano = $request->user();

        if (! $ciudadano->es_asociacion) {
            return response()->json(['data' => ['asociacion' => null]]);
        }

        $asociacion = $ciudadano->asociacion;

        return response()->json([
            'data' => [
                'asociacion' => $asociacion ? [
                    'id' => $asociacion->id,
                    'nombre' => $asociacion->nombre,
                    'tipo' => $asociacion->tipo?->value ?? $asociacion->tipo,
                    'convenio_numero' => $asociacion->convenio_numero,
                    'convenio_hasta' => $asociacion->convenio_hasta?->toDateString(),
                    'vigente' => $asociacion->esVigente(),
                ] : null,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Ciudadano $ciudadano */
        $ciudadano = $request->user();

        // tokens()->delete() en vez de currentAccessToken()->delete():
        // currentAccessToken() sale null en este setup y con ?-> el delete se
        // salta en silencio -> token vivo -> me() da 200. TRADE-OFF: cierra
        // TODAS las sesiones del ciudadano, no solo la actual. Aceptable pa' MVP.
        $ciudadano->tokens()->delete();

        return response()->json(['message' => 'Sesion cerrada']);
    }

    private function resolverAsociacionVigentePorCi(string $ci): ?Asociacion
    {
        $asociacion = Asociacion::where('responsable_ci', $ci)
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('convenio_hasta')
                ->orWhere('convenio_hasta', '>=', now()->startOfDay()))
            ->first();

        return ($asociacion && $asociacion->esVigente()) ? $asociacion : null;
    }

    private function serializar(Ciudadano $ciudadano): array
    {
        $a = $ciudadano->asociacion;

        return [
            'id' => $ciudadano->id,
            'ci' => $ciudadano->ci,
            'nombre_completo' => $ciudadano->nombre_completo,
            'telefono' => $ciudadano->telefono,
            'es_asociacion' => $ciudadano->es_asociacion,
            'asociacion' => ($ciudadano->es_asociacion && $a) ? [
                'id' => $a->id,
                'nombre' => $a->nombre,
                'tipo' => $a->tipo?->value ?? $a->tipo,
                'convenio_numero' => $a->convenio_numero,
                'convenio_hasta' => $a->convenio_hasta?->toDateString(),
                'vigente' => $a->esVigente(),
            ] : null,
        ];
    }
}
