<?php

use App\Http\Controllers\MicropubController;

/*
|--------------------------------------------------------------------------
| Micropub Routes
|--------------------------------------------------------------------------
*/

Route::get('/micropub', [MicropubController::class, 'get']);
Route::post('/micropub', [MicropubController::class, 'post']);
Route::post('/micropub/media', [MicropubController::class, 'media']);
