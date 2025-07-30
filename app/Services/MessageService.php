<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Event;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

//сервис для работы с сообщениями(Тут по названиям методов понятно что для чего, мне и в правде не лень написать 😊)
class MessageService
{
    public function sendMessage($chat_id, $sender_id, $sender_type, $content, $uuid = null): Message
    {
        $chat = Chat::findOrFail($chat_id);
        if ($chat->status !== 'active') {
            throw new \Exception('Чат не активен', 400);
        }

        if ($sender_type === 'operator' && $chat->operator_id !== $sender_id) {
            throw new \Exception('Нет доступа к этому чату', 403);
        }

        DB::beginTransaction();
        try {
            $existingMessage = $uuid ? Message::where('uuid', $uuid)
                ->where('chat_id', $chat_id)
                ->whereIn('status', ['delivered', 'read'])
                ->first() : null;

            if ($existingMessage) {
                return $existingMessage;
            }

            $message = Message::create([
                'chat_id' => $chat_id,
                'sender_id' => $sender_id,
                'sender_type' => $sender_type,
                'content' => $content,
                'status' => 'sent',
                'uuid' => $uuid ?? Str::uuid()->toString(),
                'retry_count' => 0,
            ]);

            Event::create([
                'chat_id' => $chat_id,
                'event_type' => 'message_sent',
                'sender_id' => $sender_id,
                'sender_type' => $sender_type,
                'data' => ['message_id' => $message->id],
                'uuid' => $message->uuid,
            ]);

            DB::commit();
            return $message;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function editMessage($message_id, $sender_id, $sender_type, $content): Message
    {
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
                'edited_at' => now(),
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

    public function markMessageDelivered($message_id, $recipient_id, $recipient_type): Message
    {
        $message = Message::findOrFail($message_id);
        if ($message->status !== 'sent') {
            return $message;
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
