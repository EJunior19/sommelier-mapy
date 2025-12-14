<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsistenteController;

Route::post('/responder', [AsistenteController::class, 'responder'])
    ->name('api.asistente.responder');

Route::post('/hablar', [AsistenteController::class, 'responder'])
    ->name('api.asistente.hablar');
