<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\OperatorAuthController;

Route::middleware('guest:operator')->group(function () {
    Route::get('login', [OperatorAuthController::class, 'create'])
        ->name('login');
    Route::post('login', [OperatorAuthController::class, 'login']);
});

Route::middleware('auth:operator')->group(function () {

    Route::post('logout', [OperatorAuthController::class, 'logout'])
        ->name('logout');
});
