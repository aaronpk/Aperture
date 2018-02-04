<?php

/*
|--------------------------------------------------------------------------
| Microsub Routes
|--------------------------------------------------------------------------
*/

Route::get('/microsub/{user}', 'MicrosubController@get');
Route::post('/microsub/{user}', 'MicrosubController@post');
