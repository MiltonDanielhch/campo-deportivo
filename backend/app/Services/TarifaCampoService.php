<?php

namespace App\Services;

use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\TarifaCampo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de versionado de tarifas (HU-A3).
 *
 * Cada campo puede tener hasta 2 tarifas activas (una por tipo):
 *   - diurna: bloques que inician antes de hora_inicio_noche
 *   - nocturna: bloques que inician a esa hora o después
 */
class TarifaCampoService
{
    /**
     * Crea una nueva tarifa de un tipo, cerrando automáticamente la anterior del mismo tipo.
     */
    public function actualizarTarifa(
        CampoDeportivo $campo,
        string $tipoTarifa,
        float $nuevoPrecio,
    ): TarifaCampo {
        $usuarioId = $this->funcionarioActualId();

        if ($usuarioId === null) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'No se pudo identificar al funcionario autenticado. Cerrá sesión y volvé a entrar.',
                ], 401),
            );
        }

        return DB::transaction(function () use ($campo, $tipoTarifa, $nuevoPrecio, $usuarioId): TarifaCampo {
            // 1. Lock pesimista sobre la tarifa activa del MISMO tipo
            $tarifaAnterior = TarifaCampo::where('campo_id', $campo->id)
                ->where('tipo_tarifa', $tipoTarifa)
                ->whereNull('vigente_hasta')
                ->lockForUpdate()
                ->first();

            if ($tarifaAnterior) {
                $tarifaAnterior->update(['vigente_hasta' => now()]);
            }

            // 2. Crear la nueva tarifa activa
            $nuevaTarifa = TarifaCampo::create([
                'campo_id' => $campo->id,
                'tipo_tarifa' => $tipoTarifa,
                'precio_por_hora' => $nuevoPrecio,
                'vigente_desde' => now(),
                'vigente_hasta' => null,
                'creado_por' => $usuarioId,
            ]);

            // 3. Auditoría
            AuditoriaService::registrar(
                tabla: 'tarifas_campo',
                registroId: $nuevaTarifa->id,
                accion: 'crear_tarifa',
                usuarioId: $usuarioId,
                datosAnteriores: $tarifaAnterior?->only(['id', 'tipo_tarifa', 'precio_por_hora', 'vigente_desde', 'vigente_hasta']),
                datosNuevos: $nuevaTarifa->only(['id', 'tipo_tarifa', 'precio_por_hora', 'vigente_desde', 'vigente_hasta']),
            );

            return $nuevaTarifa;
        });
    }

    /**
     * Resuelve el UUID local del funcionario autenticado.
     *
     * El middleware VerificaTokenOAuth autentica con JWT de iBARE guardado en
     * sesión, pero NO setea el guard de Laravel, así que auth()->id() es null.
     * Probamos todas las fuentes posibles, en orden:
     *   1. Guard estándar de Laravel
     *   2. Atributos inyectados al request por el middleware
     *   3. Datos de funcionario guardados en sesión
     *   4. Decodificar el JWT de sesión (sub = mamore_id) y buscar el Funcionario local
     */
    private function funcionarioActualId(): ?string
    {
        // 1) Guard estándar
        if ($id = auth()->id()) {
            return (string) $id;
        }

        $request = request();

        // 2) Atributos del request (por si el middleware los inyecta)
        foreach (['funcionario_id', 'funcionario', 'usuario_id', 'oauth_funcionario_id'] as $key) {
            $valor = $request->attributes->get($key);
            if ($id = $this->extraerId($valor)) {
                return $id;
            }
        }

        // 3) Sesión
        foreach (['funcionario_id', 'funcionario', 'usuario_id', 'user_id'] as $key) {
            $valor = session($key);
            if ($id = $this->extraerId($valor)) {
                return $id;
            }
        }

        // 4) Decodificar el JWT guardado en sesión: sub = mamore_id
        $token = session('session_token')
            ?? session('oauth_token')
            ?? session('token')
            ?? session('access_token');

        if (is_string($token) && $token !== '') {
            $sub = $this->subDelJwt($token);

            if ($sub !== null) {
                $funcionario = Funcionario::where('mamore_id', $sub)->first()
                    ?? Funcionario::where('id', $sub)->first();

                if ($funcionario) {
                    return $funcionario->id;
                }
            }
        }

        return null;
    }

    /**
     * De un valor que puede ser string, array u objeto modelo, extrae el ID.
     */
    private function extraerId(mixed $valor): ?string
    {
        if (is_string($valor) && $valor !== '') {
            return $valor;
        }
        if (is_array($valor)) {
            $id = $valor['id'] ?? null;
            return is_string($id) ? $id : null;
        }
        if (is_object($valor) && isset($valor->id)) {
            return (string) $valor->id;
        }
        return null;
    }

    /**
     * Decodifica el payload de un JWT SIN verificar firma
     * (la firma ya fue verificada por el middleware en esta misma petición).
     */
    private function subDelJwt(string $token): ?string
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }

        $b64 = strtr($partes[1], '-_', '+/');
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $payload = json_decode(base64_decode($b64), true);

        return is_array($payload) && isset($payload['sub'])
            ? (string) $payload['sub']
            : null;
    }

    /**
     * Devuelve las tarifas activas de un campo agrupadas por tipo.
     * @return array{diurna: ?TarifaCampo, nocturna: ?TarifaCampo}
     */
    public function tarifasActivas(CampoDeportivo $campo): array
    {
        $tarifas = TarifaCampo::where('campo_id', $campo->id)
            ->whereNull('vigente_hasta')
            ->get()
            ->keyBy('tipo_tarifa');

        return [
            'diurna' => $tarifas->get('diurna'),
            'nocturna' => $tarifas->get('nocturna'),
        ];
    }

    /**
     * Historial completo, ordenado por vigencia descendente.
     */
    public function historial(CampoDeportivo $campo): Collection
    {
        return TarifaCampo::where('campo_id', $campo->id)
            ->with('creadoPor:id,nombre_completo')
            ->orderByDesc('vigente_desde')
            ->get();
    }
}
