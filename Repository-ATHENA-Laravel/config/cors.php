<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración resiliente para soportar desarrollo local (Vite, Next,
    | Laragon, Valet) y entornos de producción sin depender exclusivamente
    | de variables manuales en el .env.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_unique(array_merge(
        [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            'http://localhost',
            'http://127.0.0.1',
        ],
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
        array_filter([
            env('FRONTEND_URL'),
            env('APP_URL'),
        ])
    )))),

    'allowed_origins_patterns' => [
        '#^https?://.*\.test(:\d+)?$#',
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 0,

    'supports_credentials' => true,

];
