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

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'korapay' => [
        'secret_key' => env('KORAPAY_SECRET_KEY'),
        'public_key' => env('KORAPAY_PUBLIC_KEY'),
        'withdrawal_fee' => env('KORAPAY_WITHDRAWAL_FEE_NGN'),
    ],

    'sumsub' => [
        'app_token' => env('SUMSUB_APP_TOKEN'),
        'secret_key' => env('SUMSUB_SECRET_KEY'),
        'base_url' => env(
            'SUMSUB_BASE_URL',
            'https://api.sumsub.com'
        ),
        'level_name' => env('SUMSUB_LEVEL_NAME'),
        'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET'),
        'applicant_url_template' => env('SUMSUB_APPLICANT_URL_TEMPLATE'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // The frontend destination after Laravel finishes Google login.
    // This must NOT be the same path as GOOGLE_REDIRECT_URI.
    'frontend_google_callback_url' => env(
        'FRONTEND_GOOGLE_CALLBACK_URL',
        'http://127.0.0.1:8000/google-test'
    ),

];