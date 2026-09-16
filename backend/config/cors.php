<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173', // panel web (Vite)
        'http://localhost:5174', // web-public
    ],
    // Desarrollo local: acepta cualquier puerto de localhost
    // (Vite, Flutter web con puerto aleatorio, etc.).
    // EN PRODUCCIÓN: reemplazar por los dominios reales del GAD Beni.
    'allowed_origins_patterns' => ['|^http://localhost(:\d+)?$|'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
