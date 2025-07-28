<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Client;
use App\Models\Event;
use App\Models\Message;
use App\Models\Operator;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClientChatController extends Controller
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_CLOSED = 'closed';

    protected MessageService $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

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

    private function setClientSession(Request $request, Client $client, Chat $chat): void
    {
        $request->session()->put('client_id', $client->id);
        $request->session()->put('chat_id', $chat->id);
    }

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

    public function sendMessage(Request $request, $chat_id)
    {
        Log::info('ClientChatController::sendMessage called', [
            'chat_id' => $chat_id,
            'client_id' => session('client_id') ?? $request->cookie('client_id'),
            'content' => $request->input('content'),
            'uuid' => $request->input('uuid'),
        ]);

        $clientId = session('client_id') ?? $request->cookie('client_id');
        if (!$clientId) {
            return response()->json(['error' => 'Клиент не авторизован'], 403);
        }

        $request->validate([
            'content' => 'required|string|max:1000',
            'uuid' => 'nullable|string|uuid',
        ]);

        try {
            $message = $this->messageService->sendMessage(
                $chat_id,
                $clientId,
                'client',
                $request->input('content'),
                $request->input('uuid')
            );
            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            $statusCode = is_numeric($e->getCode()) ? (int)$e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }


    public function pollMessages(Request $request, $chat_id)
    {
        $clientId = session('client_id') ?? $request->cookie('client_id');
        if (!$clientId) {
            return response()->json(['error' => 'Клиент не авторизован'], 403);
        }

        $chat = Chat::findOrFail($chat_id);
        if ($chat->client_id !== (int)$clientId) {
            return response()->json(['error' => 'Нет доступа к этому чату'], 403);
        }

        $timeout = 30;
        $startTime = time();
        $lastEventId = $request->query('last_event_id', 0);

        while (time() - $startTime < $timeout) {
            $events = Event::where('chat_id', $chat_id)
                ->where('id', '>', $lastEventId)
                ->whereIn('event_type', ['message_sent', 'typing_start', 'typing_end', 'message_delivered', 'message_edited'])
                ->where('sender_type', 'operator')
                ->get();

            $messageIds = $events->whereIn('event_type', ['message_sent', 'message_edited'])->pluck('data.message_id')->unique();
            if ($messageIds->isNotEmpty()) {
                foreach ($events->where('event_type', 'message_sent') as $event) {
                    $this->messageService->markMessageDelivered($event->data['message_id'], $clientId, 'client');
                }
            }

            $messages = Message::where('chat_id', $chat_id)->get();
            $typingEvents = $events->whereIn('event_type', ['typing_start', 'typing_end'])->map(function ($event) {
                return [
                    'event_type' => $event->event_type,
                    'sender_type' => $event->sender_type,
                ];
            });

            if ($events->isNotEmpty()) {
                return response()->json([
                    'messages' => $messages,
                    'typing_events' => $typingEvents,
                    'last_event_id' => $events->max('id'),
                ], 200);
            }

            usleep(500000);
        }

        return response()->json(['messages' => [], 'typing_events' => [], 'last_event_id' => $lastEventId], 200);
    }


    public function sendTypingEvent(Request $request, $chat_id)
    {
        Log::info('ClientChatController::sendTypingEvent called', [
            'chat_id' => $chat_id,
            'client_id' => session('client_id') ?? $request->cookie('client_id'),
            'typing' => $request->input('typing'),
        ]);

        $request->validate([
            'typing' => 'required|boolean',
        ]);

        $clientId = session('client_id') ?? $request->cookie('client_id');
        if (!$clientId) {
            return response()->json(['error' => 'Клиент не авторизован'], 403);
        }

        $chat = Chat::findOrFail($chat_id);
        if ($chat->client_id !== (int)$clientId) {
            return response()->json(['error' => 'Нет доступа к этому чату'], 403);
        }

        Event::create([
            'chat_id' => $chat_id,
            'event_type' => $request->typing ? 'typing_start' : 'typing_end',
            'sender_id' => (int)$clientId,
            'sender_type' => 'client',
            'data' => [],
        ]);

        return response()->json(['success' => true]);
    }

    public function getMessages($chat_id)
    {
        $chat = Chat::findOrFail($chat_id);

        $clientId = session('client_id') ?? request()->cookie('client_id');
        if ($chat->client_id !== (int)$clientId) {
            return response()->json(['error' => 'Unauthorized: Not your chat'], 403);
        }

        $messages = Message::where('chat_id', $chat_id)->orderBy('created_at', 'asc')->get();
        return response()->json($messages);
    }

    public function markMessageAsRead(Request $request, $chat_id)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'integer|exists:messages,id',
        ]);

        $clientId = session('client_id') ?? $request->cookie('client_id');
        if (!$clientId) {
            return response()->json(['error' => 'Клиент не авторизован'], 403);
        }

        $chat = Chat::findOrFail($chat_id);
        if ($chat->client_id !== (int)$clientId) {
            return response()->json(['error' => 'Unauthorized: Not your chat'], 403);
        }

        $messages = Message::whereIn('id', $request->message_ids)
            ->where('chat_id', $chat_id)
            ->where('sender_type', 'operator')
            ->where('status', 'delivered')
            ->get();

        DB::beginTransaction();
        try {
            foreach ($messages as $message) {
                $message->update(['status' => 'read']);
                Event::create([
                    'chat_id' => $chat_id,
                    'event_type' => 'message_read',
                    'sender_id' => (int)$clientId,
                    'sender_type' => 'client',
                    'data' => ['message_id' => $message->id],
                ]);
            }
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to mark messages as read'], 500);
        }
    }

    public function editMessage(Request $request, $chat_id, $message_id)
    {
        Log::info('ClientChatController::editMessage called', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'client_id' => session('client_id') ?? $request->cookie('client_id'),
            'content' => $request->input('content'),
        ]);

        $clientId = session('client_id') ?? $request->cookie('client_id');
        if (!$clientId) {
            return response()->json(['error' => 'Клиент не авторизован'], 403);
        }

        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        try {
            $message = $this->messageService->editMessage(
                $message_id,
                $clientId,
                'client',
                $request->input('content')
            );
            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            $statusCode = is_numeric($e->getCode()) ? (int) $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }
}
