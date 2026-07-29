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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'internal_secret' => env('FRONTEND_INTERNAL_SECRET'),
        'neutral_platform_domain' => env('NEUTRAL_PLATFORM_DOMAIN', 'localhost'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        // Flutterwave signs webhooks with a separate dashboard-configured
        // "secret hash" (sent back as the `verif-hash` header) — unlike
        // Paystack, it is never the same value as the API secret key.
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
    ],

    'platform_billing' => [
        'provider' => env('PLATFORM_PAYMENT_PROVIDER', 'paystack'),
        'default_commission_percent' => (float) env('DEFAULT_MANAGED_COMMISSION_PERCENT', 1.0),
        // Used only to estimate the up-front charge when fee_bearer =
        // "learner" — the real fee is always re-read from the provider's
        // verify-transaction response and is what actually gets recorded on
        // the Payment row. A reasonable Paystack/Flutterwave NGN ballpark;
        // tune per market if this expands beyond Nigeria.
        'estimated_provider_fee_percent' => (float) env('ESTIMATED_PROVIDER_FEE_PERCENT', 1.5),
    ],

];
