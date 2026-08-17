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
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '54'),
    ],

    'mercadopago' => [
        'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
        'point_terminal_id' => env('MERCADO_PAGO_POINT_TERMINAL_ID'),
        'point_expiration_time' => env('MERCADO_PAGO_POINT_EXPIRATION_TIME', 'PT15M'),
        'point_print_on_terminal' => env('MERCADO_PAGO_POINT_PRINT_ON_TERMINAL', 'no_ticket'),
        'platform_id' => env('MERCADO_PAGO_PLATFORM_ID'),
        'integrator_id' => env('MERCADO_PAGO_INTEGRATOR_ID'),
        'sponsor_id' => env('MERCADO_PAGO_SPONSOR_ID'),
        'timeout' => env('MERCADO_PAGO_TIMEOUT', 30),
        'connect_timeout' => env('MERCADO_PAGO_CONNECT_TIMEOUT', 5),
    ],

];
