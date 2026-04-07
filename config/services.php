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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    /*
    |--------------------------------------------------------------------------
    | IP Geolocation (Optional)
    |--------------------------------------------------------------------------
    |
    | For security/audit logs you may want to enrich requests with an IP → country
    | lookup. This can add latency and share IP addresses with a third-party,
    | so keep it disabled by default and enable explicitly in production.
    |
    */
    'ipgeo' => [
        'enabled' => env('IP_GEO_ENABLED', false),
        'base_url' => env('IP_GEO_BASE_URL', 'https://ipapi.co'),
        'timeout_seconds' => (int) env('IP_GEO_TIMEOUT', 2),
    ],

    'threepap_checker' => [
        'base_url' => env('THREEPAP_CHECKER_BASE_URL', 'https://checker.3pap.africa/api/v1'),
        'api_token' => env('THREEPAP_CHECKER_API_TOKEN'),
        'timeout' => (int) env('THREEPAP_CHECKER_TIMEOUT', 20),
    ],

];
