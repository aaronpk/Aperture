<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User, App\Channel, App\Source, App\Entry, App\Media;
use DB;

class TrimUserData extends Command
{
  protected $signature = 'data:trim';
  protected $description = 'Remove entries from users based on the per-user retention policy';

  public function handle()
  {
    $this->info(date('Y-m-d H:i:s'));

    // Find all users with a retention policy that is not unlimited
    $users = User::where('retention_days', '>', 0)->get();
    foreach($users as $user) {
      $this->info($user->url);
      $timestamp = date('Y-m-d H:i:s', time() - ($user->retention_days * 86400));
      $this->info("  Finding entries added before $timestamp");

      #if(!$this->confirm('Continue?')) { die(); }

      $channels = $user->all_channels()->get();
      foreach($channels as $channel) {
        // Find and remove entries added to this channel longer ago than the retention days
        $entries = DB::table('channel_entry')
          ->where('channel_id', $channel->id)
          ->where('created_at', '<', $timestamp)
          ->get();
        if(count($entries)) {
          $this->info("  ".$channel->id." ".$channel->name." - removing ".count($entries)." entries");
          #if(!$this->confirm('Continue?')) { die(); }
          foreach($entries as $entry) {
            $channel->remove_entries([$entry->entry_id]);
          }
        }
      }
    }

  }

}
