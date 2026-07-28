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

    'external_auth' => [
        'login_url' => env('EXTERNAL_AUTH_LOGIN_URL', 'http://crm.local/api/login'),
        'logout_url' => env('EXTERNAL_AUTH_LOGOUT_URL', 'http://crm.local/api/logout'),
    ],

    'public_catalog_proxy' => [
        'url' => env('FRONTEND_CATALOG_PROXY_URL', 'http://localhost:5173/api/v1/catalog'),
    ],

    'integration' => [
        // Comma-separated list of valid CRM integration bearer tokens.
        // When empty, token presence is still required but any non-empty token is accepted (legacy behavior).
        // In production, set INTEGRATION_ALLOWED_TOKENS to restrict access.
        'allowed_tokens' => env('INTEGRATION_ALLOWED_TOKENS', ''),
    ],

    'twentyfirst' => [
        'api_key' => env('API_KEY_21ST'),
        'agent' => env('AGENT_21ST_SLUG', 'frontend-dev-agent'),
        'token_expires_in' => env('AGENT_21ST_TOKEN_EXPIRES_IN', '1h'),
    ],

];
