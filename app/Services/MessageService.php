<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Event;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use function Psy\debug;

class MessageService
{
    public function sendMessage($chat_id, $sender_id, $sender_type, $content, $uuid = null)
    {
        Log::info('sendMessage called', [
            'chat_id' => $chat_id,
            'sender_id' => $sender_id,
            'sender_type' => $sender_type,
            'content' => $content,
            'uuid' => $uuid,

        ]);

        // Находим чат
        $chat = Chat::findOrFail($chat_id);

        // Проверяем, что чат активен
        if ($chat->status !== 'active') {
            throw new \Exception('Чат не активен', 400);
        }

        // Проверяем, что оператор имеет доступ к чату
        if ($sender_type === 'operator' && $chat->operator_id !== $sender_id) {
            throw new \Exception('Нет доступа к этому чату', 403);
        }

        // Создаём сообщение в транзакции
        DB::beginTransaction();
        try {
            $message = Message::create([
                'chat_id' => $chat_id,
                'sender_id' => $sender_id,
                'sender_type' => $sender_type,
                'content' => $content,
                'status' => 'sent',
                'uuid' => $uuid ?? Str::uuid()->toString(),
                ]);

            // Создаём событие message_sent
            Event::create([
                'chat_id' => $chat_id,
                'event_type' => 'message_sent',
                'sender_id' => $sender_id,
                'sender_type' => $sender_type,
                'data' => ['message_id' => $message->id],
                'uuid' => $uuid ?? Str::uuid()->toString(),
            ]);

            DB::commit();
            return $message;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function editMessage($message_id, $sender_id, $sender_type, $content)
    {
        Log::info('MessageService::editMessage called', [
            'message_id' => $message_id,
            'sender_id' => $sender_id,
            'sender_type' => $sender_type,
            'content' => $content,
        ]);

        $message = Message::findOrFail($message_id);
        $chat = Chat::findOrFail($message->chat_id);

        if ($message->sender_type !== $sender_type || $message->sender_id !== $sender_id) {
            throw new \Exception('Вы не можете редактировать это сообщение', 403);
        }

        if ($chat->status !== 'active') {
            throw new \Exception('Чат не активен', 400);
        }

        DB::beginTransaction();
        try {
            $message->update([
                'content' => $content,
                'updated_at' => now(),
                'is_edited' => true,
                'edited_at'=> now(),
            ]);

            Event::create([
                'chat_id' => $message->chat_id,
                'event_type' => 'message_edited',
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

    public function markMessageDelivered($message_id, $recipient_id, $recipient_type)
    {
        Log::info('MessageService::markMessageDelivered called', [
            'message_id' => $message_id,
            'recipient_id' => $recipient_id,
            'recipient_type' => $recipient_type,
        ]);

        $message = Message::findOrFail($message_id);
        if ($message->status !== 'sent') {
            return $message; // Уже доставлено или прочитано
        }

        $chat = Chat::findOrFail($message->chat_id);
        if (
            ($recipient_type === 'operator' && $chat->operator_id !== $recipient_id) ||
            ($recipient_type === 'client' && $chat->client_id !== $recipient_id)
        ) {
            throw new \Exception('Нет доступа к этому сообщению', 403);
        }

        DB::beginTransaction();
        try {
            $message->update(['status' => 'delivered']);
            Event::create([
                'chat_id' => $message->chat_id,
                'event_type' => 'message_delivered',
                'sender_id' => $recipient_id,
                'sender_type' => $recipient_type,
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
