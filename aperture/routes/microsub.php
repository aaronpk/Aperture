<?php

use App\Http\Controllers\MicrosubController;

/*
|--------------------------------------------------------------------------
| Microsub Routes
|--------------------------------------------------------------------------
*/

Route::get('/microsub/{user}', [MicrosubController::class, 'get']);
Route::post('/microsub/{user}', [MicrosubController::class, 'post']);
