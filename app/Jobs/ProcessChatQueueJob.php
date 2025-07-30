<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Event;
use App\Models\Operator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessChatQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Повторные попытки при сбоях
    public $timeout = 30; // Таймаут 30 секунд

    public function __construct()
    {
        //
    }

    public function handle()
    {
        Log::info('ProcessChatQueueJob started');

        $pendingChats = Chat::where('status', 'pending')
            ->lockForUpdate()
            ->get();

        foreach ($pendingChats as $chat) {
            $operator = Operator::withCount([
                'chats as active_chats_count' => fn($q) => $q->where('status', 'active'),
            ])
                ->orderBy('active_chats_count')
                ->lockForUpdate()
                ->get()
                ->first(fn($operator) => $operator->active_chats_count < $operator->max_chats);

            if ($operator) {
                DB::transaction(function () use ($chat, $operator) {
                    $chat->update([
                        'operator_id' => $operator->id,
                        'status' => 'active',
                        'accepted_at' => now(),
                    ]);

                    Event::create([
                        'chat_id' => $chat->id,
                        'event_type' => 'chat_assigned',
                        'sender_id' => $operator->id,
                        'sender_type' => 'operator',
                        'data' => [],
                    ]);
                });
            }
        }

        Log::info('Chat queue processed', ['pending_chats' => $pendingChats->count()]);
    }
}
