<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'paystack' => [
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
],

 'korapay' => [
    'secret_key' => env('KORAPAY_SECRET_KEY'),
 ],
 
    
    'dojah' => [
        'base_url' => env(
            'DOJAH_BASE_URL',
            'https://sandbox.dojah.io'
        ),
        'app_id' => env('DOJAH_APP_ID'),
        'secret_key' => env('DOJAH_SECRET_KEY'),
        'selfie_threshold' => (int) env(
            'DOJAH_SELFIE_THRESHOLD',
            90
        ),
    ],

];
