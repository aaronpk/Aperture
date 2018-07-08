<?php

/*
|--------------------------------------------------------------------------
| Micropub Routes
|--------------------------------------------------------------------------
*/

Route::get('/micropub', 'MicropubController@get');
Route::post('/micropub', 'MicropubController@post');
Route::post('/micropub/media', 'MicropubController@media');
Route::get('/token', 'IndieAuthController@token_get')->name('token_get');
