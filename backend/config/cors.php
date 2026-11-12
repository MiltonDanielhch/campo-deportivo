<?php

use Illuminate\Support\Str;

/**
 * Parsea CORS_ALLOWED_ORIGINS desde env.
 * Acepta: "http://a.com,http://b.com" o "http://a.com, http://b.com"
 * Devuelve array limpio, sin espacios.
 */
$parseOrigins = function (): array {
    $raw = env('CORS_ALLOWED_ORIGINS', '');
    if (empty($raw)) {
        // Fallback dev local
        return [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://localhost:5175',
        ];
    }
    return array_values(array_filter(
        array_map('trim', explode(',', $raw)),
        fn($o) => $o !== ''
    ));
};

$origins = $parseOrigins();

return [
    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // Origins explícitos desde env (producción) o localhost (dev)
    'allowed_origins' => $origins,

    // Patrón adicional: acepta localhost con cualquier puerto (dev)
    // y dominios sslip.io (para entornos temporales de Coolify)
    'allowed_origins_patterns' => [
        '|^http://localhost(:\d+)?$|',
        '|^https?://[a-z0-9]+\.190\.129\.54\.198\.sslip\.io$|',
    ],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400, // 24h cache de preflight
    'supports_credentials' => true,
];