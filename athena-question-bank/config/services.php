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

    'gemini' => [
        'api_key'        => env('GEMINI_API_KEY'),
        'model'          => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-3.5-flash'),
    ],

    'athena' => [
        'jwt_secret'     => env('ATHENA_JWT_SECRET', 'athena_saber11_fallback_secret_2026'),
        'admin_email'    => env('ATHENA_ADMIN_EMAIL', 'admin@athena.edu.co'),
        'admin_password' => env('ATHENA_ADMIN_PASSWORD', 'admin123456'),
        'admin_key'      => env('ATHENA_ADMIN_KEY', 'athena_iqpe_super_secret_admin_key_2026'),
    ],

];
