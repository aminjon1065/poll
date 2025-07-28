<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Event;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\MessageService;

class OperatorChatController extends Controller
{
    protected $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function dashboard(): \Inertia\Response
    {
        $chats = Chat::where('operator_id', Auth::guard('operator')->id())
            ->where('status', 'active')
            ->with('client')
            ->get();
        return Inertia::render('operator/Dashboard', [
            'chats' => $chats,
        ]);
    }
}
