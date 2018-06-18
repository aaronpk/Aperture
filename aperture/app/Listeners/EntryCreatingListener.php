<?php
namespace App\Listeners;

use App\Events\EntryCreating;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Log, Storage;
use App\Entry, App\Media;

class EntryCreatingListener # implements ShouldQueue
{

  public function handle(EntryCreating $event)
  {
    Redis::incr(env('APP_URL').'::entries');
  }

}
