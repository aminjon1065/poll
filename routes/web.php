<?php

use App\Http\Controllers\Operator\OperatorChatController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ClientChatController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth:operator')->group(function () {
    Route::get('/dashboard', [OperatorChatController::class, 'dashboard'])->name('dashboard');
});

Route::post('/chat/start', [ClientChatController::class, 'start'])->name('chat.start');
Route::get('/chat/{chat}', [ClientChatController::class, 'show'])->name('chat.show');


require __DIR__ . '/auth.php';
