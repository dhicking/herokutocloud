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

    'heroku' => [
        'client_id' => env('HEROKU_CLIENT_ID'),
        'client_secret' => env('HEROKU_CLIENT_SECRET'),
        'redirect_uri' => env('HEROKU_REDIRECT_URI', env('APP_URL').'/auth/heroku/callback'),
        'api_url' => env('HEROKU_API_URL', 'https://api.heroku.com'),
        'oauth_url' => env('HEROKU_OAUTH_URL', 'https://id.heroku.com'),
    ],

    'cloud' => [
        'api_url' => env('LARAVEL_CLOUD_API_URL', 'https://cloud.laravel.com/api'),
    ],

];
