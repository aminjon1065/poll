<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessChatQueueJob;
use App\Models\Chat;
use App\Models\Event;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperatorChatController extends Controller
{
    protected $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    //Тут показываем дашборд оператора
    public function dashboard(): Response
    {
        $operator = Auth::guard('operator')->user();
        $chats = Chat::where('operator_id', $operator->id)
            ->where('status', 'active')
            ->with('messages', 'client')
            ->get();

        return Inertia::render('operator/Dashboard', [
            'auth' => $operator,
            'chats' => $chats,
        ]);
    }

    //Тут связано с чатом, к примеру новые клиенты появятся в списке оператора
    public function pollChats(Request $request)
    {
        set_time_limit(30);

        $operatorId = Auth::guard('operator')->id();
        if (!$operatorId) {
            return response()->json(['error' => 'Оператор не авторизован'], 403);
        }

        $lastEventId = $request->query('last_event_id', 0);
        $timeout = 5;
        $startTime = time();
        $maxIterations = 5;

        $cacheKey = "operator_chats_{$operatorId}";
        $chats = Cache::remember($cacheKey, 10, function () use ($operatorId) {
            return Chat::where('operator_id', $operatorId)
                ->where('status', 'active')
                ->with('client')
                ->orderBy('created_at', 'desc')
                ->get();
        });
        $iteration = 0;
        while (time() - $startTime < $timeout && $iteration < $maxIterations) {
            $events = Event::where('sender_type', 'operator')
                ->where('sender_id', $operatorId)
                ->where('event_type', 'chat_assigned')
                ->where('id', '>', $lastEventId)
                ->select('id', 'chat_id', 'event_type')
                ->get();

            if ($events->isNotEmpty()) {
                Cache::forget($cacheKey);
                $chats = Chat::where('operator_id', $operatorId)
                    ->where('status', 'active')
                    ->with('client')
                    ->orderBy('created_at', 'desc')
                    ->get();
                return response()->json([
                    'chats' => $chats,
                    'last_event_id' => $events->max('id'),
                ], 200);
            }

            usleep(1000000);
            $iteration++;
        }
        return response()->json([
            'chats' => $chats,
            'last_event_id' => $lastEventId,
        ], 200);
    }

    //Тут показываем чат с сообзениями
    public function show($chat_id): Response
    {
        $chat = Chat::where('id', $chat_id)
            ->where('operator_id', Auth::guard('operator')->id())
            ->with('messages', 'client')
            ->firstOrFail();
        return Inertia::render('operator/Chat', [
            'chat' => $chat,
            'auth' => Auth::guard('operator')->user(),
            'chats' => Chat::where('operator_id', Auth::guard('operator')->id())
                ->where('status', 'active')
                ->with('messages', 'client')
                ->get(),
        ]);
    }

    // Тут закрываем чат
    public function closeChat(Request $request, $chat_id)
    {
        $chat = Chat::findOrFail($chat_id);
        if ($chat->operator_id !== Auth::id()) {
            return response()->json(['error' => 'Нет доступа к этому чату'], 403);
        }

        if ($chat->status !== 'active') {
            return response()->json(['error' => 'Чат уже закрыт или не активен'], 400);
        }

        DB::beginTransaction();
        try {
            $chat->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            Event::create([
                'chat_id' => $chat_id,
                'event_type' => 'chat_closed',
                'sender_id' => Auth::id(),
                'sender_type' => 'operator',
                'data' => [],
            ]);
            ProcessChatQueueJob::dispatchSync(); // Синхронный вызов
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Не удалось закрыть чат'], 500);
        }
    }

    //Тут отправляем сообщение
    public function sendMessage(Request $request, $chat_id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'uuid' => 'nullable|string|uuid',
        ]);

        try {
            $message = $this->messageService->sendMessage(
                $chat_id,
                Auth::id(),
                'operator',
                $request->input('content'),
                $request->input('uuid')
            );
            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            $statusCode = is_numeric($e->getCode()) ? (int)$e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }

    //Тут получаем всё что связано с сообщениями
    public function pollMessages(Request $request, $chat_id)
    {
        $chat = Chat::findOrFail($chat_id);
        if ($chat->operator_id !== Auth::id()) {
            return response()->json(['error' => 'Нет доступа к этому чату'], 403);
        }

        $timeout = 30;
        $startTime = time();
        $lastEventId = $request->query('last_event_id', 0);

        while (time() - $startTime < $timeout) {
            $events = Event::where('chat_id', $chat_id)
                ->where('id', '>', $lastEventId)
                ->whereIn('event_type', [
                    'message_sent',
                    'typing_start',
                    'typing_end',
                    'message_delivered',
                    'message_edited',
                    'chat_closed',
                    'client_name_updated',
                ])
                ->where('sender_type', 'client')
                ->get();

            $messageIds = $events->whereIn('event_type', ['message_sent', 'message_edited'])
                ->pluck('data.message_id')
                ->unique();
            if ($messageIds->isNotEmpty()) {
                foreach ($events->where('event_type', 'message_sent') as $event) {
                    $this->messageService->markMessageDelivered($event->data['message_id'], Auth::id(), 'operator');
                }
            }

            $messages = Message::where('chat_id', $chat_id)->get();
            $typingEvents = $events->whereIn('event_type', ['typing_start', 'typing_end'])->map(function ($event) {
                return [
                    'event_type' => $event->event_type,
                    'sender_type' => $event->sender_type,
                ];
            });

            $clientName = null;
            $nameUpdateEvent = $events->where('event_type', 'client_name_updated')->last();
            if ($nameUpdateEvent) {
                $clientName = $nameUpdateEvent->data['name'] ?? null;
            }

            if ($events->isNotEmpty()) {
                $chatClosed = $events->where('event_type', 'chat_closed')->isNotEmpty();
                return response()->json([
                    'messages' => $messages,
                    'typing_events' => $typingEvents,
                    'last_event_id' => $events->max('id'),
                    'chat_status' => $chat->refresh()->status,
                    'chat_closed' => $chatClosed,
                    'client_name' => $clientName,
                ], 200);
            }

            usleep(500000);
        }

        return response()->json([
            'messages' => [],
            'typing_events' => [],
            'last_event_id' => $lastEventId,
            'chat_status' => $chat->status,
            'chat_closed' => false,
            'client_name' => null,
        ], 200);
    }

    //Тут отправляем что пишем
    public function sendTypingEvent(Request $request, $chat_id)
    {
        $request->validate([
            'typing' => 'required|boolean',
        ]);

        $chat = Chat::findOrFail($chat_id);
        if ($chat->operator_id !== Auth::id()) {
            return response()->json(['error' => 'Нет доступа к этому чату'], 403);
        }

        Event::create([
            'chat_id' => $chat_id,
            'event_type' => $request->input('typing') ? 'typing_start' : 'typing_end',
            'sender_id' => Auth::id(),
            'sender_type' => 'operator',
            'data' => [],
        ]);

        return response()->json(['success' => true], 200);
    }

    //Тут отправляем что прочитали сообщение
    public function markMessageAsRead(Request $request, $chat_id)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'integer|exists:messages,id',
        ]);

        $chat = Chat::findOrFail($chat_id);
        if ($chat->operator_id !== Auth::id()) {
            return response()->json(['error' => 'Нет доступа к этому чату'], 403);
        }

        $messages = Message::whereIn('id', $request->message_ids)
            ->where('chat_id', $chat_id)
            ->where('sender_type', 'client')
            ->where('status', 'delivered')
            ->get();

        DB::beginTransaction();
        try {
            foreach ($messages as $message) {
                $message->update(['status' => 'read']);
                Event::create([
                    'chat_id' => $chat_id,
                    'event_type' => 'message_read',
                    'sender_id' => Auth::id(),
                    'sender_type' => 'operator',
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

    //Тут редактируем сообщение
    public function editMessage(Request $request, $chat_id, $message_id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        try {
            $message = $this->messageService->editMessage(
                $message_id,
                Auth::id(),
                'operator',
                $request->input('content')
            );
            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            $statusCode = is_numeric($e->getCode()) ? (int)$e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }
}
