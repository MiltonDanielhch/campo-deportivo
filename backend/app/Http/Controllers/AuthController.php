<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function loginRedirect()
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        Session::put('oauth_state', $state);
        Session::put('oauth_code_verifier', $codeVerifier);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.ibare.client_id'),
            'redirect_uri' => config('services.ibare.redirect_uri'),
            'scope' => 'canchas:admin',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect(config('services.ibare.base_url').'/oauth/authorize?'.$params);
    }

    public function callback(Request $request)
    {
        \Log::info('Callback recibido', [
            'code' => $request->code ? 'presente' : 'ausente',
            'state' => $request->state,
            'error' => $request->error,
            'error_description' => $request->error_description,
            'session_state' => Session::get('oauth_state'),
        ]);

        if ($request->filled('error')) {
            $error = $request->input('error');
            $description = $request->input('error_description', '');
            \Log::warning("Ibare devolvió error en callback", [
                'error' => $error,
                'description' => $description,
                'hint' => $request->input('hint'),
            ]);

            return redirect(config('services.ibare.spa_url')."/?auth_error={$error}");
        }

        if (! $request->code || $request->state !== Session::get('oauth_state')) {
            \Log::warning('State no coincide o code ausente', [
                'request_state' => $request->state,
                'session_state' => Session::get('oauth_state'),
                'has_code' => !empty($request->code),
            ]);

            return redirect(config('services.ibare.spa_url').'/?auth_error=state');
        }

        \Log::info('Canjeando código con Ibare', [
            'client_id' => config('services.ibare.client_id'),
            'redirect_uri' => config('services.ibare.redirect_uri'),
        ]);

        $response = Http::asForm()->post(config('services.ibare.base_url').'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.ibare.client_id'),
            'client_secret' => config('services.ibare.client_secret'),
            'redirect_uri' => config('services.ibare.redirect_uri'),
            'code' => $request->code,
            'code_verifier' => Session::get('oauth_code_verifier'),
        ]);

        \Log::info('Respuesta de Ibare', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        Session::forget(['oauth_state', 'oauth_code_verifier']);

        if (! $response->successful()) {
            \Log::error('Error al canjear código en Ibare', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return redirect(config('services.ibare.spa_url').'/?auth_error=canje');
        }

        $this->guardarTokens($response->json());

        \Log::info('Tokens guardados en sesión', [
            'has_access_token' => !empty(Session::get('access_token')),
            'has_refresh_token' => !empty(Session::get('refresh_token')),
        ]);

        return redirect(config('services.ibare.spa_url'));
    }

    public function me(Request $request)
    {
        $funcionario = $request->attributes->get('funcionario');

        // Cargar relación 'rol' para evitar N+1 y obtener todos sus campos
        $funcionario->loadMissing('rol');

        return response()->json([
            'data' => [
                'id' => $funcionario->id,
                'nombre_completo' => $funcionario->nombre_completo,
                'ci' => $funcionario->ci,
                'usuario' => $funcionario->usuario,
                'estado' => $funcionario->estado,
                'rol' => $funcionario->rol ? [
                    'id' => $funcionario->rol->id,
                    'nombre' => $funcionario->rol->nombre,
                    'descripcion' => $funcionario->rol->descripcion,
                    'permisos' => $funcionario->rol->permisos ?? [],
                ] : null,
                'creado_en' => $funcionario->creado_en?->toISOString(),
            ],
        ]);
    }

    public function logout()
    {
        Session::forget(['access_token', 'refresh_token', 'token_expires_at']);

        return response()->json(['message' => 'Sesion cerrada']);
    }

    private function guardarTokens(array $tokens): void
    {
        Session::put('access_token', $tokens['access_token']);
        Session::put('refresh_token', $tokens['refresh_token'] ?? null);
        Session::put('token_expires_at', now()->addSeconds($tokens['expires_in'] ?? 600));
    }
}
