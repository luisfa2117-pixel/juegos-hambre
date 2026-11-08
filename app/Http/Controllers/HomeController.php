<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** La portada: si ya has entrado, directo a tus partidas; si no, qué es esto y el botón de probar */
class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        return $request->user() !== null ? redirect()->route('games.index') : view('home');
    }
}
