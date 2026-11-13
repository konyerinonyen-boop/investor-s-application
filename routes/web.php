
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/kyc', 'kyc.index')->name('kyc.page');

// Browser-based OAuth initiation and callback.
// These routes use Laravel's web session for Socialite state validation.
Route::get('/auth/google/redirect/{transaction}', [
    GoogleAuthController::class,
    'redirectToGoogle',
])->middleware('throttle:10,1')->name('google.redirect');

Route::get('/auth/google/callback', [
    GoogleAuthController::class,
    'handleGoogleCallback',
])->middleware('throttle:20,1')->name('google.callback');
