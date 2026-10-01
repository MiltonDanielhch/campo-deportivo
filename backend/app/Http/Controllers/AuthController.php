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

        $tokenUrl = config('services.ibare.base_url').'/oauth/token';
        $clientId = config('services.ibare.client_id');
        $clientSecret = config('services.ibare.client_secret');
        $redirectUri = config('services.ibare.redirect_uri');
        $codeVerifier = Session::get('oauth_code_verifier');

        \Log::info('=== INICIO diagnóstico OAuth2 Ibare ===', [
            'token_url' => $tokenUrl,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ]);

        $payloadBase = [
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $request->code,
            'code_verifier' => $codeVerifier,
        ];

        // ─── Variante 1: POST body con client_id y client_secret ───
        \Log::info('Variante 1: POST body con credenciales');
        $response1 = Http::timeout(10)->asForm()->post($tokenUrl, array_merge($payloadBase, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]));
        \Log::info('Respuesta Variante 1', [
            'status' => $response1->status(),
            'body' => $response1->json(),
        ]);

        // ─── Variante 2: Basic Auth (credentials en header) ───
        \Log::info('Variante 2: Basic Auth');
        $response2 = Http::timeout(10)
            ->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($tokenUrl, $payloadBase);
        \Log::info('Respuesta Variante 2', [
            'status' => $response2->status(),
            'body' => $response2->json(),
        ]);

        // ─── Variante 3: Solo client_id en body (cliente público) ───
        \Log::info('Variante 3: Solo client_id (cliente público)');
        $response3 = Http::timeout(10)->asForm()->post($tokenUrl, array_merge($payloadBase, [
            'client_id' => $clientId,
        ]));
        \Log::info('Respuesta Variante 3', [
            'status' => $response3->status(),
            'body' => $response3->json(),
        ]);

        // Tomar la primera que funcione
        $response = null;
        $varianteGanadora = null;
        foreach ([$response1, $response2, $response3] as $i => $r) {
            if ($r->successful()) {
                $response = $r;
                $varianteGanadora = $i + 1;
                break;
            }
        }

        Session::forget(['oauth_state', 'oauth_code_verifier']);

        if (!$response || $response->failed()) {
            \Log::error('=== FIN diagnóstico: ninguna variante funcionó ===', [
                'variante1_status' => $response1->status(),
                'variante2_status' => $response2->status(),
                'variante3_status' => $response3->status(),
            ]);

            return redirect(config('services.ibare.spa_url').'/?auth_error=canje');
        }

        \Log::info("=== FIN diagnóstico: Variante {$varianteGanadora} funcionó ===");

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
