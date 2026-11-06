<?php

use App\Http\Controllers\CharacterController;
use Illuminate\Support\Facades\Route;

// De momento la portada lleva a los personajes; en la 4.2 llevará a las partidas
Route::redirect('/', '/characters');

// Crea las 7 rutas típicas (index, create, store, show, edit, update, destroy) menos show
Route::resource('characters', CharacterController::class)->except('show');
