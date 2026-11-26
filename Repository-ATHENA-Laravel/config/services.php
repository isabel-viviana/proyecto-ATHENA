<?php

return [
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
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'question_bank' => [
        'base_url'  => env('QUESTION_BANK_BASE_URL', 'http://127.0.0.1:8001/api/v1'),
        'admin_key' => env('QUESTION_BANK_ADMIN_KEY', 'athena_iqpe_super_secret_admin_key_2026'),
        'timeout'   => (int) env('QUESTION_BANK_TIMEOUT', 10),
    ],

    'wompi' => [
        'public_key' => env('WOMPI_PUBLIC_KEY'),
        'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),
        'events_secret' => env('WOMPI_EVENTS_SECRET'),
        'environment' => env('WOMPI_ENVIRONMENT', 'test'),
        'checkout_url' => env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'),
        'redirect_url' => env('WOMPI_REDIRECT_URL'),
    ],

];
