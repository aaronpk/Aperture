<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            Route::pattern('user', '[0-9]+');
            Route::pattern('channel', '[0-9]+');
            Route::pattern('source_id', '[0-9]+');
            Route::pattern('entry', '[0-9a-zA-Z@\#]+');
            Route::pattern('channel_uid', '[0-9a-zA-Z]+');

            // web.php applies the "web" group itself, since some routes in it must not use cookies
            Route::group([], base_path('routes/web.php'));

            Route::middleware('websub')->group(base_path('routes/websub.php'));
            Route::middleware('microsub')->group(base_path('routes/microsub.php'));
            Route::middleware('micropub')->group(base_path('routes/micropub.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('websub', []);
        $middleware->group('microsub', [
            \App\Http\Middleware\VerifyIndieAuthAccessToken::class,
        ]);
        $middleware->group('micropub', [
            \App\Http\Middleware\VerifyChannelApiKey::class,
        ]);

        $middleware->alias([
            'munin' => \App\Http\Middleware\MuninToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
