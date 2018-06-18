<?php
namespace App\Listeners;

use App\Events\EntryDeleting;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Log, Storage;
use App\Entry, App\Media;

class EntryDeletingListener # implements ShouldQueue
{

  public function handle(EntryDeleting $event)
  {
    Redis::incr(env('APP_URL').'::entries');
  }

}
