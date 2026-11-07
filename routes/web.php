<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameEventController;
use App\Http\Controllers\SponsorGiftController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

// Solo para quien NO ha iniciado sesión (si ya has entrado, te manda dentro)
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('login', [SessionController::class, 'create'])->name('login');
    // throttle: como mucho 5 intentos por minuto, para frenar a quien pruebe contraseñas a lo bruto
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:5,1');
});

// Todo lo demás exige haber iniciado sesión (si no, te manda a la ruta llamada "login")
Route::middleware('auth')->group(function () {
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/games');

    Route::resource('games', GameController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('games/{game}/gifts', [SponsorGiftController::class, 'store'])->name('games.gifts.store');
    Route::resource('characters', CharacterController::class);
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');

    // Los eventos son comunes a todos: solo los administradores pueden editarlos
    Route::middleware('can:manage-events')->group(function () {
        Route::post('events/preview', [GameEventController::class, 'preview'])->name('events.preview');
        Route::resource('events', GameEventController::class)->except('show');
    });
});
