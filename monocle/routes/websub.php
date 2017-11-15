<?php

/*
|--------------------------------------------------------------------------
| WebSub Routes
|--------------------------------------------------------------------------
*/

Route::post('/websub/source/{token}', 'WebSubController@source_callback')->name('source_callback');
