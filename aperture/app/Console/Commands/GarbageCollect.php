<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User, App\Channel, App\Source, App\Entry, App\Media;
use DB;

class GarbageCollect extends Command
{
  protected $signature = 'garbage-collect';
  protected $description = 'Clean up old entries and files that are no longer needed';

  public function handle()
  {
    /*
    // Find all users with a retention policy that is not unlimited
    $users = User::where('retention_days', '>', 0)->get();
    foreach($users as $user) {
      $this->info($user->url);
      $timestamp = date('Y-m-d H:i:s', time() - ($user->retention_days * 86400));
      $this->info("  Removing entries added before $timestamp");

      $channels = $user->all_channels()->get();
      foreach($channels as $channel) {
        // Find and remove entries added to this channel longer ago than the retention days
        $entries = $channel->entries()->where('channel_entry.created_at', '<', $timestamp)->get();
        $this->info("  ".$channel->name." - removing ".count($entries)." entries");
        foreach($entries as $entry) {
          $channel->remove_entries([$entry->id]);
        }
      }
    }
    */

    // Find entries that are not in any channels now
    $entries = Entry::select('entries.*')
      ->leftJoin('channel_entry', ['channel_entry.entry_id'=>'entries.id'])
      ->whereNull('channel_entry.id')
      ->get();
    foreach($entries as $entry) {
      $this->info($entry->id.' '.$entry->created_at.' '.$entry->unique);
      // Delete the model which triggers deleting associated files
      $entry->delete();
    }

  }

}
