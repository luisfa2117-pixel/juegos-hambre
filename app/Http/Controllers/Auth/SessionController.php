<?php

namespace App\Http\Controllers\Auth;

use App\Actions\DeleteAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Iniciar y cerrar sesión */
class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Pon tu correo.',
            'email.email' => 'Ese correo no parece válido.',
            'password.required' => 'Pon tu contraseña.',
        ]);

        // attempt() busca el usuario por el correo y compara la contraseña cifrada
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Mismo mensaje tanto si el correo no existe como si la contraseña está mal:
            // así nadie puede averiguar qué correos tienen cuenta
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        // Nueva sesión tras entrar: evita que alguien reutilice una sesión antigua (session fixation)
        $request->session()->regenerate();

        // intended(): si te mandaron al login desde otra página, vuelves a ella
        return redirect()->intended(route('games.index'));
    }

    public function destroy(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Un invitado no puede volver a entrar (no tiene contraseña): al salir, se borra todo lo suyo
        if ($user->is_guest) {
            $deleteAccount->handle($user);

            return redirect()->route('home')->with('status', 'Tu cuenta de invitado y sus partidas se han borrado.');
        }

        return redirect()->route('login');
    }
}
