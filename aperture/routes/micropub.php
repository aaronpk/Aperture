<?php

/*
|--------------------------------------------------------------------------
| Micropub Routes
|--------------------------------------------------------------------------
*/

Route::get('/micropub', 'MicropubController@get');
Route::post('/micropub', 'MicropubController@post');
Route::post('/micropub/media', 'MicropubController@media');
