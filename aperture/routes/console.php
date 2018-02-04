<?php

use Illuminate\Foundation\Inspiring;
use App\User, App\Source, App\Channel, App\Entry;


/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('test', function(){

  $source = Source::where('url','http://scratch.dev/blog/')->first();
  Log::info($source->id);
  Log::info(json_encode($source->channels()->get()));

});
