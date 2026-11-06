<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameEventController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/games');

Route::resource('games', GameController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

Route::resource('characters', CharacterController::class)->except('show');

// La vista previa del formulario de eventos (la llama el JavaScript con fetch)
Route::post('events/preview', [GameEventController::class, 'preview'])->name('events.preview');
Route::resource('events', GameEventController::class)->except('show');
