<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/kyc', 'kyc.index')->name('kyc.page');

Route::view('/google-test', 'auth.google-test')->name('google.test');

Route::get('/auth/google/redirect', [
    GoogleAuthController::class,
    'redirectToGoogle'
])->middleware('throttle:10,1');

Route::get('/auth/google/callback', [
    GoogleAuthController::class,
    'handleGoogleCallback'
])->middleware('throttle:20,1');