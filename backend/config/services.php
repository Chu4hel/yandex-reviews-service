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

    'admin' => [
        'api_key' => env('ADMIN_API_KEY', 'georeviews_secret_admin_key_2026'),
    ],

    'yandex' => [
        // Намеренное ограничение до 12 страниц * 50 = 600 отзывов для защиты от блокировок и капчи
        'max_sync_pages' => env('YANDEX_MAX_SYNC_PAGES', 12),
    ],

    'proxy' => [
        'check_url' => env('PROXY_CHECK_URL', 'https://ya.ru'),
        'check_timeout' => (int) env('PROXY_CHECK_TIMEOUT', 5),
    ],

];
