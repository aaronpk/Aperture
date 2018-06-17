<?php
namespace App\Listeners;

use App\Events\EntryDeleting;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log, Storage;
use App\Entry, App\Media;

class EntryDeletingListener # implements ShouldQueue
{

  public function handle(EntryDeleting $event)
  {
    $media = $event->entry->media()->get();
    foreach($media as $file) {
      // Check if this file is used by any other entries
      if($file->entries()->count() == 1) {
        $file->delete();
      }
    }
  }

}
