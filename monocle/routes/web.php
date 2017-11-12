<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('index');

Route::get('/dashboard', 'HomeController@index')->name('dashboard');

Route::post('/channel/new', 'HomeController@create_channel')->name('create_channel');
Route::get('/channel/{channel}', 'HomeController@channel')->name('channel');
Route::post('/channel/{channel}/add_source', 'HomeController@add_source')->name('add_source');
Route::post('/channel/{channel}/remove_source', 'HomeController@remove_source')->name('remove_source');
Route::post('/channel/{channel}/add_apikey', 'HomeController@add_apikey')->name('add_apikey');

Route::post('/source/find_feeds', 'HomeController@find_feeds')->name('find_feeds');


Route::get('/login', 'LoginController@login')->name('login');
Route::get('/logout', 'LoginController@logout')->name('logout');
Route::post('/login', 'LoginController@start');
Route::get('/login/callback', 'LoginController@callback')->name('login_callback');
