<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\IndieAuthController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MicropubController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatsController;

Route::middleware('web')->group(function(){
    Route::get('/', function() {
      return view('welcome');
    })->name('index');

    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
    Route::get('/docs', [PublicController::class, 'docs'])->name('docs');
    Route::get('/pricing', [PublicController::class, 'pricing'])->name('pricing');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/save', [SettingsController::class, 'save'])->name('settings_save');
    Route::post('/settings/reload_micropub_config', [SettingsController::class, 'reload_micropub_config'])->name('reload_micropub_config');

    Route::post('/channel/new', [HomeController::class, 'create_channel'])->name('create_channel');
    Route::get('/channel/{channel}', [HomeController::class, 'channel'])->name('channel');
    Route::post('/channel/{channel}/save', [HomeController::class, 'save_channel'])->name('save_channel');
    Route::post('/channel/{channel}/delete', [HomeController::class, 'delete_channel'])->name('delete_channel');
    Route::post('/channel/{channel}/add_source', [HomeController::class, 'add_source'])->name('add_source');
    Route::post('/channel/{channel}/remove_source', [HomeController::class, 'remove_source'])->name('remove_source');
    Route::post('/channel/{channel}/add_apikey', [HomeController::class, 'add_apikey'])->name('add_apikey');
    Route::post('/channel/set_order', [HomeController::class, 'set_channel_order'])->name('set_channel_order');

    Route::post('/source/find_feeds', [HomeController::class, 'find_feeds'])->name('find_feeds');
    Route::post('/channel/{channel}/source/{source}/save', [HomeController::class, 'save_source'])->name('save_source');

    Route::get('/login', [LoginController::class, 'login'])->name('login');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/login', [LoginController::class, 'start']);
    Route::get('/login/callback', [LoginController::class, 'callback'])->name('login_callback');

    Route::get('/auth', [IndieAuthController::class, 'auth_get'])->name('auth_get');
    Route::post('/auth/process', [IndieAuthController::class, 'auth_process'])->name('auth_process');

    Route::get('/{user_b60}-{channel_b60}-{source_b60}', [IndieAuthController::class, 'profile'])->name('profile_url');

    Route::get('/entry/{source_id}/{entry}', [MicropubController::class, 'entry'])->name('entry');
});

// No cookies on these routes since they are used by API clients
Route::post('/auth', [IndieAuthController::class, 'auth_post'])->name('auth_post');
Route::post('/token', [IndieAuthController::class, 'token_post'])->name('token_post');

Route::post('/api/register', [LoginController::class, 'api_register']);

Route::get('/stats/users', [StatsController::class, 'users']);
Route::get('/stats/new_entries', [StatsController::class, 'new_entries']);
Route::get('/stats/entries', [StatsController::class, 'entries']);
Route::get('/stats/entries_size', [StatsController::class, 'entries_size']);
Route::get('/stats/sources', [StatsController::class, 'sources']);
Route::get('/stats/media_size', [StatsController::class, 'media_size']);
Route::get('/stats/proxy_bytes', [StatsController::class, 'proxy_bytes']);
