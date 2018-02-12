<?php

/*
|--------------------------------------------------------------------------
| WebSub Routes
|--------------------------------------------------------------------------
*/

Route::post('/websub/source/{token}', 'WebSubReceiverController@source_callback')->name('source_callback');
