<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        if (Auth::attempt(['login' => strtolower(trim($data['login'])), 'password' => $data['password'], 'ativo' => true], $request->boolean('lembrar'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors(['login' => 'Usuário ou senha incorretos.'])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
