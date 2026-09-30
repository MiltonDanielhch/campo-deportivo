<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'recaudaciones' => [
        // ─── Claves existentes (las lee el Simulado y código actual) ───
        'url' => env('RECAUDACIONES_API_URL'),
        'token' => env('RECAUDACIONES_API_TOKEN'),
        // Solo aplica cuando el binding activo es el Simulado (local/testing)
        'simulado_modo' => env('RECAUDACIONES_SIMULADO_MODO', 'exito'),
        'webhook_secret' => env('RECAUDACIONES_WEBHOOK_SECRET'),

        // ─── Nuevas (Fase 8.1): OAuth2 client_credentials contra SIREB ───
        'client_id' => env('RECAUDACIONES_API_CLIENT_ID', 'sedede'),
        'client_secret' => env('RECAUDACIONES_API_CLIENT_SECRET'),
        // Pendiente de confirmar con Ibare; default razonable mientras tanto
        'token_url' => env(
            'RECAUDACIONES_API_TOKEN_URL',
            env('RECAUDACIONES_API_URL', 'https://test.sireb.beni.gob.bo').'/oauth/token'
        ),
        'sucursal_id' => env('RECAUDACIONES_API_SUCURSAL_ID'),
        'timeout_seconds' => (int) env('RECAUDACIONES_TIMEOUT_SEGUNDOS', 10),
        // true = inyecta el Simulado (comportamiento actual); false = cliente real
        'simulador_habilitado' => env('RECAUDACIONES_SIMULADOR_HABILITADO', true),

        // Credenciales del SISTEMA para SIREB (client_credentials vía Ibare).
        // Separadas del bloque 'ibare' (que es el login de funcionarios humanos).
        'oauth' => [
            'token_url' => env('SIREB_TOKEN_URL'),
            'client_id' => env('SIREB_CLIENT_ID', 'sedede'),
            'client_secret' => env('SIREB_CLIENT_SECRET'),
        ],
    ],

    'ibare' => [
        'base_url' => env('IBARE_BASE_URL'),
        'jwks_url' => env('IBARE_JWKS_URL'),
        'issuer' => env('IBARE_ISSUER'),
        'audience' => env('IBARE_AUDIENCE'),
        'client_id' => env('IBARE_CLIENT_ID'),
        'client_secret' => env('IBARE_CLIENT_SECRET'),
        'redirect_uri' => env('IBARE_REDIRECT_URI'),
        'spa_url' => env('IBARE_SPA_URL'),
    ],

];
