<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RetryPendingMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct()
    {
        //
    }

    public function handle(MessageService $messageService)
    {
        $timeoutSeconds = 10;
        $maxRetries = 3;
        Message::where('status', 'sent')
            ->where('created_at', '<', now()->subSeconds($timeoutSeconds))
            ->where('retry_count', '<', $maxRetries)
            ->chunk(100, function ($messages) use ($messageService, $maxRetries) {
                foreach ($messages as $message) {
                    DB::beginTransaction();
                    try {
                        if ($message->status !== 'sent') {
                            DB::commit();
                            continue;
                        }
                        $existingMessage = Message::where('uuid', $message->uuid)
                            ->where('id', '!=', $message->id)
                            ->whereIn('status', ['delivered', 'read'])
                            ->exists();

                        if ($existingMessage) {
                            $message->update(['status' => 'delivered']);
                            DB::commit();
                            continue;
                        }
                        $newMessage = $messageService->sendMessage(
                            $message->chat_id,
                            $message->sender_id,
                            $message->sender_type,
                            $message->content,
                            $message->uuid
                        );
                        $message->update([
                            'status' => 'replaced',
                            'retry_count' => $message->retry_count + 1,
                        ]);

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $message->increment('retry_count');
                        if ($message->retry_count >= $maxRetries) {
                            $message->update(['status' => 'failed']);
                        }
                    }
                }
            });

    }
}
