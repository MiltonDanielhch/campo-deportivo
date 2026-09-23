<?php

namespace App\Http\Middleware;

use App\Models\Funcionario;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resource Server OAuth2: valida JWT emitidos por Ibare contra su JWKS.
 * Ibare certifica identidad (sub = mamore_id); la autorización
 * ("qué puede hacer acá") es 100% local, según docs de Ibare.
 */
class VerificaTokenOAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        try {
            $payload = $this->validarToken($token);
        } catch (\Exception $e) {
            Log::warning('Token OAuth inválido: '.$e->getMessage());

            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        // Autorización local: sin asignación en canchas, no entra (patrón SIREB)
        $funcionario = Funcionario::where('mamore_id', $payload->sub)->first();

        if (! $funcionario || $funcionario->estado !== 'activo') {
            return response()->json([
                'error' => 'FUNCIONARIO_NO_HABILITADO',
                'mensaje' => 'Tu cuenta no tiene asignación en este sistema.',
            ], 403);
        }

        $request->setUserResolver(fn () => $funcionario);
        $request->attributes->set('funcionario', $funcionario);

        return $next($request);
    }

     private function validarToken(string $token): object
    {
        Log::debug('Validando token', ['token' => substr($token, 0, 50)]);

        $payload = JWT::decode($token, $this->obtenerKeySet());

        Log::debug('Payload decodificado', ['iss' => $payload->iss, 'sub' => $payload->sub]);
        Log::debug('Issuer esperado', ['expected' => config('services.ibare.issuer')]);

        if (($payload->iss ?? null) !== config('services.ibare.issuer')) {
            Log::error('Issuer inválido', ['payload_iss' => $payload->iss, 'expected' => config('services.ibare.issuer')]);
            throw new \Exception('Issuer inválido');
        }

        return $payload;
    }

    private function obtenerKeySet(): array
    {
        $jwks = Cache::remember('ibare_jwks', 600, function () {
            $response = Http::get(config('services.ibare.jwks_url'));

            if (! $response->successful()) {
                throw new \Exception('No se pudo obtener el JWKS de Ibare');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }
}
