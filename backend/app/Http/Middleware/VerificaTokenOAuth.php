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
use Illuminate\Support\Facades\Session;

/**
 * Resource Server OAuth2: valida JWT emitidos por Ibare contra su JWKS.
 * Ibare certifica identidad (sub = mamore_id); la autorización
 * ("qué puede hacer acá") es 100% local, según docs de Ibare.
 */
class VerificaTokenOAuth
{
    public function handle(Request $request, Closure $next)
    {
        // === LOGS DE DIAGNÓSTICO ===
        \Log::info('Middleware VerificaTokenOAuth ejecutado', [
            'path' => $request->path(),
            'expected_cookie_name' => config('session.cookie'),
            'received_cookies' => $request->header('cookie'),
            'has_session' => $request->hasSession(),
            'session_id' => $request->session()->getId(),
            'session_token' => $request->session()->get('access_token'),
        ]);
        // ============================

        $token = $request->bearerToken();
        $desdeSesion = false;

        if (! $token) {
            $token = $request->session()->get('access_token');
            $desdeSesion = true;
        }

        if (! $token) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        try {
            $payload = $this->validarToken($token);
        } catch (\Exception $e) {
            // Si el token vino de la sesión y está expirado, intentamos refresh transparente
            if ($desdeSesion && $this->estaExpirado($token)) {
                $nuevo = $this->refrescarSesion();

                if (! $nuevo) {
                    return response()->json(['error' => 'Sesion expirada'], 401);
                }

                $token = $nuevo;

                try {
                    $payload = $this->validarToken($token);
                } catch (\Exception $e2) {
                    return response()->json(['error' => 'Token inválido o expirado'], 401);
                }
            } else {
                \Log::warning('Token OAuth inválido: '.$e->getMessage());

                return response()->json(['error' => 'Token inválido o expirado'], 401);
            }
        }

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

    private function estaExpirado(string $token): bool
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return false;
        }
        $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), false);

        return isset($payload->exp) && $payload->exp < time();
    }

    private function refrescarSesion(): ?string
    {
        $refreshToken = Session::get('refresh_token');

        if (! $refreshToken) {
            return null;
        }

        $response = Http::asForm()->post(config('services.ibare.base_url').'/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.ibare.client_id'),
            'client_secret' => config('services.ibare.client_secret'),
            'refresh_token' => $refreshToken,
        ]);

        if (! $response->successful()) {
            Session::forget(['access_token', 'refresh_token', 'token_expires_at']);

            return null;
        }

        $tokens = $response->json();
        Session::put('access_token', $tokens['access_token']);
        Session::put('refresh_token', $tokens['refresh_token'] ?? $refreshToken);
        Session::put('token_expires_at', now()->addSeconds($tokens['expires_in'] ?? 600));

        return $tokens['access_token'];
    }

    private function validarToken(string $token): object
    {
        Log::debug('Validando token', ['token' => substr($token, 0, 50)]);

        $payload = JWT::decode($token, $this->obtenerKeySet());

        // Convertimos a array para evitar "Undefined property" en PHP 8+
        $payloadArray = (array) $payload;

        Log::debug('Payload decodificado', ['payload' => $payloadArray]);

        // Validamos el issuer SOLO si el payload lo incluye.
        // Si Ibare no lo emite en el access_token, lo omitimos (la firma JWKS ya garantiza la procedencia).
        $expectedIssuer = config('services.ibare.issuer');
        if (isset($payloadArray['iss']) && $expectedIssuer) {
            if ($payloadArray['iss'] !== $expectedIssuer) {
                Log::error('Issuer inválido', [
                    'payload_iss' => $payloadArray['iss'],
                    'expected' => $expectedIssuer
                ]);
                throw new \Exception('Issuer inválido');
            }
        }

        // Validamos que exista el 'sub' (mamore_id), que es crítico para la autorización local
        if (!isset($payloadArray['sub'])) {
            throw new \Exception('Token sin claim "sub" (mamore_id)');
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
