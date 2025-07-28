<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OperatorChatController extends Controller
{
    public function dashboard(): \Inertia\Response
    {
        return Inertia::render('operator/Dashboard');
    }
}
