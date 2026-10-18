<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /** /login — usa a última empresa lembrada no cookie, se houver. */
    public function form(Request $request)
    {
        $empresa = null;
        if ($slug = $request->cookie('fbre_empresa')) {
            $empresa = Empresa::where('slug', $slug)->where('ativo', true)->first();
        }
        return view('auth.login', ['empresa' => $empresa]);
    }

    /** /{slug} — tela de login com a identidade da empresa. */
    public function formEmpresa(string $slug)
    {
        $empresa = Empresa::where('slug', $slug)->where('ativo', true)->firstOrFail();
        return response()
            ->view('auth.login', ['empresa' => $empresa])
            ->cookie('fbre_empresa', $empresa->slug, 60 * 24 * 365);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        $cred = ['login' => strtolower(trim($data['login'])), 'password' => $data['password'], 'ativo' => true];

        // se a tela era de uma empresa específica, só aceita usuários dela (super-admin entra em qualquer)
        if ($slug = $request->input('empresa')) {
            $empresa = Empresa::where('slug', $slug)->first();
            $user = User::where('login', $cred['login'])->first();
            if ($empresa && $user && ! $user->super_admin && $user->empresa_id !== $empresa->id) {
                return back()->withErrors(['login' => 'Usuário ou senha incorretos.'])->onlyInput('login');
            }
        }

        if (Auth::attempt($cred, $request->boolean('lembrar'))) {
            $request->session()->regenerate();
            $resp = redirect()->intended('/');
            $emp = Auth::user()->empresa;
            return $emp ? $resp->cookie('fbre_empresa', $emp->slug, 60 * 24 * 365) : $resp;
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
