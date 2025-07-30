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

    public $tries = 3;
    public $timeout = 30;

    public function handle()
    {

        $pendingChats = Chat::where('status', 'pending')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        if ($pendingChats->isEmpty()) {
            return;
        }

        foreach ($pendingChats as $chat) {
            $operator = Operator::select('operators.*')
                ->addSelect([
                    'active_chats_count' => Chat::selectRaw('COUNT(*)')
                        ->whereColumn('operator_id', 'operators.id')
                        ->where('status', 'active'),
                ])
                ->having('active_chats_count', '<', \DB::raw('operators.max_chats'))
                ->orderBy('active_chats_count')
                ->lockForUpdate()
                ->first();

            if ($operator) {
                try {
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
                } catch (\Exception $e) {
                    continue;
                }
            } else {
                Log::debug('Нет доступных операторов', [
                    'chat_id' => $chat->id,
                ]);
            }
        }
    }
}
