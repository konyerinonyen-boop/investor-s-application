<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InvestmentController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\PortfolioController;
use App\Http\Middleware\LogApiActivity;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', LogApiActivity::class])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/opportunities', [OpportunityController::class, 'index']);
        Route::get('/opportunities/{id}', [OpportunityController::class, 'show']);
        Route::get('/kyc', [KycController::class, 'status']);
        Route::post('/kyc/start', [KycController::class, 'start']);
        Route::post('/kyc/submit', [KycController::class, 'submit']);
        Route::post('/investments/equity', [InvestmentController::class, 'equity']);
        Route::post('/loans/apply', [InvestmentController::class, 'loan']);
        Route::get('/portfolio', [PortfolioController::class, 'summary']);
    });
});
