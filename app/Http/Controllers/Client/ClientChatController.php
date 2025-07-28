<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Client;
use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClientChatController extends Controller
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_CLOSED = 'closed';

    /**
     * Инициализация чата или возврат существующего.
     */
    public function start(Request $request)
    {
        $client = $this->getOrCreateClient($request);

        $chat = Chat::where('client_id', $client->id)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_ACTIVE])
            ->first();

        if (!$chat) {
            $chat = DB::transaction(function () use ($client) {
                $operator = $this->findAvailableOperatorForUpdate();
                return Chat::create([
                    'client_id' => $client->id,
                    'operator_id' => $operator?->id,
                    'status' => $operator ? self::STATUS_ACTIVE : self::STATUS_PENDING,
                    'accepted_at' => $operator ? now() : null,
                ]);
            });
        }
        $this->setClientSession($request, $client, $chat);
        return redirect()->route('chat.show', ['chat' => $chat->id]);
    }

    /**
     * Отображение чата клиента.
     */
    public function show(Chat $chat, Request $request)
    {
        $clientId = session('client_id') ?? $request->cookie('client_id');

        abort_if(!$clientId || $chat->client_id !== (int)$clientId, 403, 'Unauth');

        $this->setClientSession($request, Client::find($clientId), $chat);

        return Inertia::render('client/Chat', [
            'chat' => $chat->load('messages'),
        ]);
    }


    private function getOrCreateClient(Request $request): Client
    {
        $clientId = session('client_id') ?? $request->cookie('client_id');
        $client = $clientId ? Client::find($clientId) : null;

        if (!$client) {
            $client = Client::create([
                'name' => $request->input('name', 'Анонимный'),
                'session_id' => Str::uuid()->toString(),
            ]);
            Cookie::queue('client_id', (string)$client->id, 60 * 24 * 30); // 30 дней
        }
        return tap($client)->update(['last_active_at' => now()]);
    }

    /**
     * Сохраняем client_id и chat_id в сессию.
     */
    private function setClientSession(Request $request, Client $client, Chat $chat): void
    {
        $request->session()->put('client_id', $client->id);
        $request->session()->put('chat_id', $chat->id);
    }

    /**
     * Поиск доступного оператора с минимальной загрузкой.
     */
    private function findAvailableOperatorForUpdate(): ?Operator
    {
        return Operator::withCount([
            'chats as active_chats_count' => fn($q) => $q->where('status', self::STATUS_ACTIVE),
        ])
            ->orderBy('active_chats_count')
            ->lockForUpdate()
            ->get()
            ->first(fn($operator) => $operator->active_chats_count < $operator->max_chats);
    }
}
