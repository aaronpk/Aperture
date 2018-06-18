<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User, App\Channel, App\Source, App\Entry, App\Media;
use DB;

class GarbageCollect extends Command
{
  protected $signature = 'data:garbagecollect';
  protected $description = 'Clean up old entries and files that are no longer referenced';

  public function handle()
  {
    $this->info(date('Y-m-d H:i:s'));

    $this->info("Finding orphaned entries...");
    // Find entries that are not in any channels now
    Entry::select('entries.*')
      ->leftJoin('channel_entry', ['channel_entry.entry_id'=>'entries.id'])
      ->whereNull('channel_entry.id')
      ->chunk(100, function($entries){

      $this->info("Processing chunk");
      foreach($entries as $entry) {
        $this->info($entry->id.' '.$entry->created_at.' '.$entry->unique);
        // Delete the model which triggers deleting associated files
        $entry->delete();
        #if(!$this->confirm('Continue?')) { die(); }
      }

    });

  }

}
