<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\User, App\Channel, App\Source, App\Entry, App\Media;
use DB;

class UpdateEntryCount extends Command
{
  protected $signature = 'data:update_entry_count';
  protected $description = 'Update the cached redis count of entries';

  public function handle()
  {
    $count = Entry::count();
    $this->info($count.' entries');
    Redis::set(env('APP_URL').'::entries', $count);
  }
}
