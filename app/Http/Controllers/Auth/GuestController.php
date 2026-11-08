<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateGuestAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** El botón "Probar sin registrarse" */
class GuestController extends Controller
{
    public function __invoke(Request $request, CreateGuestAccount $createGuestAccount): RedirectResponse
    {
        $user = $createGuestAccount->handle();

        // remember: true crea una cookie de larga duración: al volver otro día, sigues siendo tú
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('games.index')->with(
            'status',
            'Ya puedes jugar: tienes 12 tributos listos. Tu cuenta de invitado se guarda en este navegador.',
        );
    }
}
