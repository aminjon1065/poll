<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Event;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OperatorChatController extends Controller
{
    protected $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function dashboard(): Response
    {

        return Inertia::render('operator/Dashboard', [
            'auth' => Auth::guard('operator')->user(),
        ]);
    }

    public function show($chat_id): Response
    {
        $chat = Chat::where('id', $chat_id)
            ->where('operator_id', Auth::guard('operator')->id())
            ->with('messages')
            ->with('client')
            ->firstOrFail();
        return Inertia::render('operator/Chat', [
            'chat' => $chat,
            'auth' => Auth::guard('operator')->user(),
        ]);
    }

    public function sendMessage(Request $request, $chat_id)
    {
        // Проверяем, что в запросе есть текст сообщения
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
            // Приводим код ошибки к числу, на случай если он строка
            $statusCode = is_numeric($e->getCode()) ? (int) $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }


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
                ->whereIn('event_type', ['message_sent', 'typing_start', 'typing_end', 'message_delivered', 'message_edited'])
                ->where('sender_type', 'client')
                ->get();

            $messageIds = $events->whereIn('event_type', ['message_sent', 'message_edited'])->pluck('data.message_id')->unique();
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
        Log::info('OperatorChatController::sendTypingEvent called', [
            'chat_id' => $chat_id,
            'operator_id' => Auth::id(),
            'typing' => $request->input('typing'),
        ]);

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

    public function editMessage(Request $request, $chat_id, $message_id)
    {
        Log::info('OperatorChatController::editMessage called', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'operator_id' => Auth::id(),
            'content' => $request->input('content'),
        ]);

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
            $statusCode = is_numeric($e->getCode()) ? (int) $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }
}
