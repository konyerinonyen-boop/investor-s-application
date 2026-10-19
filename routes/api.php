<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BytidWebhookController;
use App\Http\Controllers\Api\V1\InvestmentController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\PortfolioController;
use App\Http\Controllers\Auth\ResendVerificationEmailController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\LogApiActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;





Route::prefix('v1')->middleware(LogApiActivity::class)->group(function () {
    Route::post('/register', [AuthController::class,'register'])->middleware('throttle:10,1');
/*
|--------------------------------------------------------------------------
| Email Verification
|--------------------------------------------------------------------------
*/

Route::get(
    '/email/verify/{id}/{hash}',
    VerifyEmailController::class)->middleware([
        'signed',
        'throttle:6,1',
    ])
    ->name('verification.verify');
/*
|--------------------------------------------------------------------------
| Resend Verification Email
|--------------------------------------------------------------------------
|
| This endpoint requires the user to be authenticated.
|
*/
Route::post(
    '/email/verification-notification',
    ResendVerificationEmailController::class)->middleware([
        'auth:sanctum',
        'throttle:6,1',
    ])
    ->name('verification.send');

    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:5,1');

    Route::post('/password/verify-otp', [AuthController::class, 'verifyPasswordResetOtp'])
    
    ->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/opportunities', [OpportunityController::class, 'index']);
        Route::get('/opportunities/{id}', [OpportunityController::class, 'show']);
        
        Route::get('/kyc', [KycController::class, 'status']);
        Route::post('/kyc/update', [KycController::class, 'update'])->middleware('kyc_verified');
        Route::post('/kyc/submit', [KycController::class, 'submit'])->middleware('kyc_verified');
                Route::post(
            '/kyc/bytid/liveness',
            [KycController::class, 'startBytidLiveness']
        )->middleware('kyc_verified');

        Route::post(
            '/kyc/bytid/{session_id}/complete',
            [KycController::class, 'completeBytidLiveness']
        );


        Route::post('/investments/equity', [InvestmentController::class, 'equity']);
        Route::post('/investments/{equity}/payment', [PaymentController::class, 'initializeInvestmentPayment']);
        Route::post('/payments/{payment}/verify', [PaymentController::class, 'verifyPayment']);
        Route::post('/loans/apply', [InvestmentController::class, 'loan']);
         Route::post('/loans/{loan}/payment', [PaymentController::class, 'fundLoan']);
        Route::get('/portfolio', [PortfolioController::class, 'summary']);
      Route::post('/wallet/deposit', [
        PaymentController::class,
        'depositToWallet',
    ])->middleware('throttle:5,1');

    });
       Route::post('webhook/{provider}', [PaymentController::class, 'webhook']);

        Route::post('/webhooks/bytid', BytidWebhookController::class)
    ->middleware('throttle:120,1');
});


