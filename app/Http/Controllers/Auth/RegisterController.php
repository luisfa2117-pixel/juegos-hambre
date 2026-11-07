<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateStarterCharacters;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, CreateStarterCharacters $createStarterCharacters): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // confirmed: tiene que coincidir con el campo password_confirmation
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Pon tu nombre.',
            'email.required' => 'Pon tu correo.',
            'email.email' => 'Ese correo no parece válido.',
            'email.unique' => 'Ya hay una cuenta con ese correo.',
            'password.required' => 'Elige una contraseña.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
        ]);

        // El cast 'hashed' del modelo cifra la contraseña: nunca se guarda tal cual
        $user = User::create($data);
        $createStarterCharacters->handle($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('games.index')
            ->with('status', 'Cuenta creada. Ya tienes ' . CreateStarterCharacters::COUNT . " personajes para tu primera partida, {$user->name}.");
    }
}
