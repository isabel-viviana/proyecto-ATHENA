<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Configurado de forma resiliente para desarrollo
    | local por defecto (Vite, Laragon .test, localhost y 127.0.0.1).
    |
    */

    'stateful' => array_values(array_filter(array_unique(array_merge(
        [
            'localhost',
            'localhost:5173',
            'localhost:3000',
            'localhost:8000',
            'localhost:8080',
            '127.0.0.1',
            '127.0.0.1:5173',
            '127.0.0.1:3000',
            '127.0.0.1:8000',
            '::1',
            '*.test',
            '*.test:5173',
            '*.test:8000',
        ],
        explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', '')),
        array_filter([
            parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
            env('FRONTEND_URL') ? (
                parse_url((string) env('FRONTEND_URL'), PHP_URL_HOST) .
                (parse_url((string) env('FRONTEND_URL'), PHP_URL_PORT) ? ':' . parse_url((string) env('FRONTEND_URL'), PHP_URL_PORT) : '')
            ) : null,
        ])
    )))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
