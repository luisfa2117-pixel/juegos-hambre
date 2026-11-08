<?php

use App\Http\Controllers\Auth\GuestController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameEventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SponsorGiftController;
use App\Http\Controllers\StatsController;
use App\Http\Middleware\TouchLastSeen;
use Illuminate\Support\Facades\Route;

// ---- Páginas públicas ----

Route::get('/', HomeController::class)->name('home');

Route::view('acerca', 'about')->name('about');

// ---- Solo para quien NO ha iniciado sesión ----
Route::middleware('guest')->group(function () {
    // Como mucho 10 cuentas de invitado por hora desde la misma conexión
    Route::post('invitado', GuestController::class)->middleware('throttle:10,60')->name('guest.store');

    Route::get('login', [SessionController::class, 'create'])->name('login');
    // throttle: como mucho 5 intentos por minuto, para frenar a quien pruebe contraseñas a lo bruto
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:5,1');

    // En el servidor el registro está cerrado (REGISTRATION_ENABLED=false): las rutas ni existen
    if (config('juegos.registration')) {
        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:5,60');
    }
});

// ---- Todo lo demás exige haber iniciado sesión ----
Route::middleware(['auth', TouchLastSeen::class])->group(function () {
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');

    Route::resource('games', GameController::class)->only(['index', 'create', 'show', 'destroy']);
    // Jugar y regalar ejecutan el motor: con límite, para que nadie lo machaque a peticiones
    Route::post('games', [GameController::class, 'store'])->middleware('throttle:20,1')->name('games.store');
    Route::post('games/{game}/gifts', [SponsorGiftController::class, 'store'])->middleware('throttle:20,1')->name('games.gifts.store');

    Route::resource('characters', CharacterController::class);
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');

    // Los eventos son comunes a todos: solo los administradores pueden editarlos
    Route::middleware('can:manage-events')->group(function () {
        Route::post('events/preview', [GameEventController::class, 'preview'])->name('events.preview');
        Route::resource('events', GameEventController::class)->except('show');
    });
});
