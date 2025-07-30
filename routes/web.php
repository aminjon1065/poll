<?php

use App\Http\Controllers\Operator\OperatorChatController;
use App\Http\Controllers\Client\ClientChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth:operator')->group(function () {
    Route::get('/dashboard', [OperatorChatController::class, 'dashboard'])->name('dashboard');
    Route::get('/operator/chat/{chat_id}', [OperatorChatController::class, 'show'])->name('operator.chat.show');
    Route::get('/operator/chat/poll/{chat_id}', [OperatorChatController::class, 'pollMessages'])->name('operator.chat.poll');
    Route::post('/operator/chat/{chat_id}/send', [OperatorChatController::class, 'sendMessage'])->name('operator.chat.send');
    Route::put('/operator/chat/{chat_id}/message/{message_id}', [OperatorChatController::class, 'editMessage'])->name('operator.chat.edit');
    Route::post('/operator/chat/{chat_id}/typing', [OperatorChatController::class, 'sendTypingEvent'])->name('operator.chat.typing');
    Route::post('/operator/chat/{chat_id}/read', [OperatorChatController::class, 'markMessageAsRead'])->name('operator.chat.read');
    Route::get('/operator/poll-chats', [OperatorChatController::class, 'pollChats'])->name('operator.poll.chats');
    Route::post('/operator/chat/{chat_id}/close', [OperatorChatController::class, 'closeChat'])->name('operator.chat.close');
});

Route::middleware('auth.session')->group(function () {
    Route::get('/client/chat/poll/{chat_id}', [ClientChatController::class, 'pollMessages'])->name('client.chat.poll');
    Route::post('/client/chat/{chat_id}/send', [ClientChatController::class, 'sendMessage'])->name('client.chat.send');
    Route::get('/client/chat/{chat_id}/messages', [ClientChatController::class, 'getMessages'])->name('client.chat.messages');
    Route::post('/client/chat/{chat_id}/typing', [ClientChatController::class, 'sendTypingEvent'])->name('client.chat.typing');
    Route::post('/client/chat/{chat_id}/read', [ClientChatController::class, 'markMessageAsRead'])->name('client.chat.read');
    Route::post('/client/chat/{chat_id}/edit/{message_id}', [ClientChatController::class, 'editMessage'])->name('client.chat.edit');
    Route::post('/client/chat/{chat_id}/close', [ClientChatController::class, 'closeChat'])->name('client.chat.close');
    Route::post('/client/chat/{chat_id}/update-name', [ClientChatController::class, 'updateName'])->name('client.chat.update-name');
});

Route::post('/chat/start', [ClientChatController::class, 'start'])->name('chat.start');
Route::get('/chat/{chat}', [ClientChatController::class, 'show'])->name('chat.show');

require __DIR__ . '/auth.php';
