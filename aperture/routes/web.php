<?php

Route::middleware('web')->group(function(){
    Route::get('/', function() {
      return view('welcome');
    })->name('index');

    Route::get('/dashboard', 'HomeController@dashboard')->name('dashboard');
    Route::get('/docs', 'PublicController@docs')->name('docs');
    Route::get('/pricing', 'PublicController@pricing')->name('pricing');

    Route::get('/settings', 'SettingsController@index')->name('settings');
    Route::post('/settings/save', 'SettingsController@save')->name('settings_save');
    Route::post('/settings/reload_micropub_config', 'SettingsController@reload_micropub_config')->name('reload_micropub_config');

    Route::post('/channel/new', 'HomeController@create_channel')->name('create_channel');
    Route::get('/channel/{channel}', 'HomeController@channel')->name('channel');
    Route::post('/channel/{channel}/save', 'HomeController@save_channel')->name('save_channel');
    Route::post('/channel/{channel}/delete', 'HomeController@delete_channel')->name('delete_channel');
    Route::post('/channel/{channel}/add_source', 'HomeController@add_source')->name('add_source');
    Route::post('/channel/{channel}/remove_source', 'HomeController@remove_source')->name('remove_source');
    Route::post('/channel/{channel}/add_apikey', 'HomeController@add_apikey')->name('add_apikey');
    Route::post('/channel/set_order', 'HomeController@set_channel_order')->name('set_channel_order');

    Route::post('/source/find_feeds', 'HomeController@find_feeds')->name('find_feeds');

    Route::get('/login', 'LoginController@login')->name('login');
    Route::get('/logout', 'LoginController@logout')->name('logout');
    Route::post('/login', 'LoginController@start');
    Route::get('/login/callback', 'LoginController@callback')->name('login_callback');

    Route::get('/auth', 'IndieAuthController@auth_get')->name('auth_get');
    Route::post('/auth/process', 'IndieAuthController@auth_process')->name('auth_process');

    Route::get('/{user_b60}-{channel_b60}-{source_b60}', 'IndieAuthController@profile')->name('profile_url');

    Route::get('/entry/{source_id}/{entry}', 'MicropubController@entry')->name('entry');
});

// No cookies on these routes since they are used by API clients
Route::post('/auth', 'IndieAuthController@auth_post')->name('auth_post');
Route::post('/token', 'IndieAuthController@token_post')->name('token_post');

Route::post('/api/register', 'LoginController@api_register');

Route::get('/stats/users', 'StatsController@users');
Route::get('/stats/new_entries', 'StatsController@new_entries');
Route::get('/stats/entries', 'StatsController@entries');
Route::get('/stats/entries_size', 'StatsController@entries_size');
Route::get('/stats/sources', 'StatsController@sources');
Route::get('/stats/media_size', 'StatsController@media_size');
Route::get('/stats/proxy_bytes', 'StatsController@proxy_bytes');
