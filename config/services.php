<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'ai' => [
        'provider' => env('AI_PROVIDER'),
        'timeout' => env('AI_TIMEOUT', 20),
        // Transient 429/5xx (model overload) are retried before falling back.
        'retries' => env('AI_RETRIES', 2),
        'retry_delay_ms' => env('AI_RETRY_DELAY_MS', 500),
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            // "gemini-flash-latest" always points at the current stable flash
            // model, so the app does not break when a dated model is retired.
            'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        ],
        'openai' => [
            'key' => env('OPENAI_API_KEY', env('AI_API_KEY')),
            'model' => env('OPENAI_MODEL', env('AI_MODEL', 'gpt-4.1-mini')),
        ],
    ],

];
