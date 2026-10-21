<?php
namespace App\Listeners;

use App\Events\EntryCreating;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Log, Storage;
use App\Models\Entry, App\Models\Media;

class EntryCreatingListener # implements ShouldQueue
{

  public function handle(EntryCreating $event)
  {
    Redis::incr(config('app.url').'::entries');
  }

}
