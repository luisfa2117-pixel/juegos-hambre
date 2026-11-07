<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameEventController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/games');

Route::resource('games', GameController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

Route::resource('characters', CharacterController::class);

Route::get('stats', [StatsController::class, 'index'])->name('stats.index');

// La vista previa del formulario de eventos (la llama el JavaScript con fetch)
Route::post('events/preview', [GameEventController::class, 'preview'])->name('events.preview');
Route::resource('events', GameEventController::class)->except('show');
