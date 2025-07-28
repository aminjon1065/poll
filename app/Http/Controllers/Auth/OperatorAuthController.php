<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OperatorAuthController extends Controller
{
    public function create(): \Inertia\Response
    {
       return Inertia::render('operator/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        if (Auth::guard('operator')->attempt($credentials)) {
            $request->session()->regenerate();
            return Inertia::location(route('dashboard'));
        }
        return Inertia::render('operator/Login', [
            'error' => 'Неверный логин или пароль',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('operator')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return Inertia::location(route('login'));
    }
}
