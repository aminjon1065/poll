<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Event;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function sendMessage($chat_id, $sender_id, $sender_type, $content)
    {
        $chat = Chat::findOrFail($chat_id);

        // Проверка: чат должен быть активным
        if ($chat->status !== 'active') {
            throw new \Exception('Chat is not active', 400);
        }

        // Проверка доступа
        if ($sender_type === 'operator' && $chat->operator_id !== $sender_id) {
            throw new \Exception('Не авторизован: Не ваш чат', 403);
        }
        if ($sender_type === 'client' && $chat->client_id !== $sender_id) {
            throw new \Exception('Не авторизован: Не ваш чат', 403);
        }

        DB::beginTransaction();
        try {
            $message = Message::create([
                'chat_id' => $chat_id,
                'sender_id' => $sender_id,
                'sender_type' => $sender_type,
                'content' => $content,
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
            return $message;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function editMessage($chat_id, $message_id, $sender_id, $content)
    {
        $message = Message::where('chat_id', $chat_id)->where('id', $message_id)->firstOrFail();

        // Проверка: только оператор может редактировать свои сообщения
        if ($message->sender_type !== 'operator' || $message->sender_id !== $sender_id) {
            throw new \Exception('Нет вам доступа: Не могёте редактировать', 403);
        }

        DB::beginTransaction();
        try {
            $message->update([
                'content' => $content,
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            Event::create([
                'chat_id' => $chat_id,
                'event_type' => 'message_edited',
                'sender_id' => $sender_id,
                'sender_type' => 'operator',
                'data' => ['message_id' => $message->id],
            ]);

            DB::commit();
            return $message;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
