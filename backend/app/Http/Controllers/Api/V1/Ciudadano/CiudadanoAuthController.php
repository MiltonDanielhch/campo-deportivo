<?php

namespace App\Http\Controllers\Api\V1\Ciudadano;

use App\Http\Controllers\Controller;
use App\Models\Asociacion;
use App\Models\Ciudadano;
use App\Services\AsociacionVigenteResolver;
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
            'telefono' => $this->normalizarTelefonoNacional($validated['telefono'] ?? null),
            'password_hash' => Hash::make($validated['password']),
        ]);

        if ($vinculada) {
            $ciudadano->es_asociacion = true;
            $ciudadano->asociacion_id = $asociacion->id;
            $ciudadano->save();
        }

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

        // [revinculacion] El admin puede dar de alta (o inhabilitar) la asociacion
        // DESPUES de que el presidente se registro. El registro solo vincula al
        // crear la cuenta; sin esto, quien se registro antes queda congelado como
        // ciudadano comun aunque ahora su CI sea responsable de una asociacion
        // vigente. Re-sincroniza con la MISMA llave del registro (no debilita la
        // norma: sigue exigiendo que el GAD haya dado de alta la asociacion).
        $this->resincronizarVinculacion($ciudadano);
        // refresh() recarga atributos Y limpia relations cacheadas: sin esto,
        // loadMissing('asociacion') de abajo devolveria la asociacion vieja (null).
        $ciudadano->refresh();

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

        $ciudadano->tokens()->delete();

        return response()->json(['message' => 'Sesion cerrada']);
    }

    /**
     * [fix-teléfono-origen] Normaliza el teléfono al formato nacional de 8
     * dígitos ANTES de guardarlo, para que la BD sea fuente de verdad limpia y
     * el prellenado del front no hereda `+591` (que el regex `^[0-9]{8}$` de
     * FormularioSolicitante rechaza y, con el campo readOnly, atrapa al
     * ciudadano en "Continuar al pago"). Misma regla que el helper del front:
     * streapea no-dígitos y quita `591` solo si el resto son exactamente 8.
     *
     * "Falla ruidoso": sin dígitos útiles -> null (el front abre el campo pa'
     * corregir); dígitos parciales (ej. "12345") -> se guardan tal cual y el
     * front los muestra editables (no los mutila a 8 en silencio). Lo que
     * viaja al Core/SIREB (HU-D8) es el valor del FORMULARIO ya validado por
     * zod a 8 dígitos, nunca esta columna, así que guardar best-effort es
     * inocuo para el cobro y solo afecta al prellenado.
     */
    private function normalizarTelefonoNacional(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $raw);

        if ($digitos === null || $digitos === '') {
            return null;
        }

        if (strlen($digitos) === 11 && str_starts_with($digitos, '591')) {
            return substr($digitos, 3);
        }

        return $digitos;
    }

    private function resolverAsociacionVigentePorCi(string $ci): ?Asociacion
    {
        return app(AsociacionVigenteResolver::class)->porCi($ci);
    }

    /**
     * [revinculacion] Prende/apaga es_asociacion+asociacion_id segun la tabla
     * del GAD, en AMBOS sentidos (alta posterior y baja/vencimiento). Idempotente:
     * solo save() si el estado en memoria difiere del resuelto.
     */
    private function resincronizarVinculacion(Ciudadano $ciudadano): void
    {
        $asociacion = $this->resolverAsociacionVigentePorCi($ciudadano->ci);
        $debeEstarVinculado = $asociacion !== null;
        $estaVinculado = (bool) $ciudadano->es_asociacion;

        if ($debeEstarVinculado === $estaVinculado
            && (! $debeEstarVinculado || (string) $ciudadano->asociacion_id === (string) $asociacion->id)) {
            return;
        }

        $ciudadano->es_asociacion = $debeEstarVinculado;
        $ciudadano->asociacion_id = $debeEstarVinculado ? $asociacion->id : null;
        $ciudadano->save();
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
