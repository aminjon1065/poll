<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Event;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OperatorChatController extends Controller
{
    public function dashboard(): \Inertia\Response
    {
        return Inertia::render('operator/Dashboard');
    }

    public function sendMessage(Request $request, $chat_id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $chat = Chat::findOrFail($chat_id);

        // Проверка: оператор может отправлять сообщения только в свои активные чаты
        if (auth()->guard('operator')->check() && $chat->operator_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized: Not your chat'], 403);
        }

        // Проверка: чат должен быть активным
        if ($chat->status !== 'active') {
            return response()->json(['error' => 'Chat is not active'], 400);
        }

        $sender = auth()->user();
        $sender_type = auth()->guard('client')->check() ? 'client' : 'operator';
        $sender_id = $sender->id;

        DB::beginTransaction();
        try {
            $message = Message::create([
                'chat_id' => $chat_id,
                'sender_id' => $sender_id,
                'sender_type' => $sender_type,
                'content' => $request['content'],
                'status' => 'sent',
            ]);

            Event::create([
                'chat_id' => $chat_id,
                'event_type' => 'message_sent',
                'sender_id' => $sender_id,
                'sender_type' => $sender_type,
                'data' => ['message_id' => $message->id],
            ]);

            DB::commit();
            return response()->json(['message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }
}
