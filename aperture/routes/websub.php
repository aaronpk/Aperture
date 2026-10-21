<?php

use App\Http\Controllers\WebSubReceiverController;

/*
|--------------------------------------------------------------------------
| WebSub Routes
|--------------------------------------------------------------------------
*/

Route::post('/websub/source/{token}', [WebSubReceiverController::class, 'source_callback'])->name('source_callback');
