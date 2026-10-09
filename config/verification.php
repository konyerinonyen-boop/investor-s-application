<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verification Link Expiration
    |--------------------------------------------------------------------------
    |
    | Number of minutes before an email verification link expires.
    |
    */

    'expire' => env('EMAIL_VERIFICATION_EXPIRE', 60),

    /*
    |--------------------------------------------------------------------------
    | Resend Throttle
    |--------------------------------------------------------------------------
    |
    | Number of verification emails that can be requested within
    | the configured throttle window.
    |
    */

    'throttle' => env('EMAIL_VERIFICATION_THROTTLE', 6),

];