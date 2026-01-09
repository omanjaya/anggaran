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

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'provider' => env('WHATSAPP_PROVIDER', 'fonnte'), // fonnte, wablas, waapi

        // Fonnte (https://fonnte.com/)
        'fonnte_token' => env('FONNTE_TOKEN'),

        // Wablas (https://wablas.com/)
        'wablas_token' => env('WABLAS_TOKEN'),
        'wablas_domain' => env('WABLAS_DOMAIN', 'jogja.wablas.com'),

        // WhatsApp Business API (Meta)
        'wa_token' => env('WHATSAPP_TOKEN'),
        'wa_phone_id' => env('WHATSAPP_PHONE_ID'),
    ],

];
