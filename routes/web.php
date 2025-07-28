<?php

use App\Http\Controllers\Operator\OperatorChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth:operator')->group(function () {
    Route::get('/dashboard', [OperatorChatController::class, 'dashboard'])->name('dashboard');
});
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
