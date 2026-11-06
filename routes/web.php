<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/games');

Route::resource('games', GameController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

// Crea las rutas típicas de un CRUD menos show
Route::resource('characters', CharacterController::class)->except('show');
