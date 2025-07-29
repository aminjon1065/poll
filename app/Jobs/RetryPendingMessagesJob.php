<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryPendingMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Максимум 3 попытки
    public $timeout = 30; // Таймаут 30 секунд

    public function __construct()
    {
        //
    }

    public function handle(MessageService $messageService)
    {
        Log::info('RetryPendingMessagesJob started');

        $timeoutSeconds = 10;
        $maxRetries = 3;

        $messages = Message::where('status', 'sent')
            ->where('created_at', '<', now()->subSeconds($timeoutSeconds))
            ->where('retry_count', '<', $maxRetries)
            ->get();

        foreach ($messages as $message) {
            Log::info('Retrying message', [
                'message_id' => $message->id,
                'uuid' => $message->uuid,
                'retry_count' => $message->retry_count + 1,
            ]);

            $messageService->sendMessage(
                $message->chat_id,
                $message->sender_id,
                $message->sender_type,
                $message->content,
                $message->uuid
            );

            $message->increment('retry_count');
        }

        Log::info('Messages retried', ['count' => $messages->count()]);
    }
}
