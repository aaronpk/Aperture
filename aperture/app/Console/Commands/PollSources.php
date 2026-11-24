<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Source;
use App\Jobs\FetchSource;
use Log;

class PollSources extends Command
{
  protected $signature = 'sources:poll';
  protected $description = 'Queue a fetch of every source that is due to be checked';

  // A queued fetch that hasn't run after this long is assumed lost, and is queued again
  const QUEUED_FETCH_EXPIRES_MINUTES = 60;

  public function handle()
  {
    $max = (int)config('aperture.poll.max_per_run');
    $max_per_host = (int)config('aperture.poll.max_per_host');

    $now = now();

    // Sources that are due, most overdue first. Only sources that are in a channel
    // are polled, so removing a source from its last channel stops the polling.
    $query = Source::where('url', '!=', '')
      // Stopped after failing for too long. Following it again or retrying it resumes it.
      ->whereNull('stopped_at')
      ->whereExists(function($q) {
        $q->selectRaw('1')->from('channel_source')->whereColumn('channel_source.source_id', 'sources.id');
      })
      ->where(function($q) use($now) {
        $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', $now);
      })
      ->where(function($q) use($now) {
        $q->whereNull('fetch_queued_at')
          ->orWhere('fetch_queued_at', '<', $now->copy()->subMinutes(self::QUEUED_FETCH_EXPIRES_MINUTES));
      })
      ->orderByRaw('next_check_at IS NULL DESC')
      ->orderBy('next_check_at')
      ->orderBy('id');

    $queued = 0;
    $held_back = 0;
    $per_host = [];

    // Look further than the limit, because some sources will be held back by the per-host limit
    foreach($query->limit($max * 5)->get() as $source) {
      if($queued >= $max)
        break;

      $host = strtolower((string)parse_url($source->url, PHP_URL_HOST));
      $per_host[$host] = ($per_host[$host] ?? 0) + 1;
      if($per_host[$host] > $max_per_host) {
        // Stays due, and goes out on a later run
        $held_back++;
        continue;
      }

      $source->timestamps = false;
      $source->fetch_queued_at = $now;
      $source->save();

      FetchSource::dispatch($source->id);
      $queued++;
    }

    $message = 'Queued '.$queued.' sources'.($held_back ? ', held back '.$held_back.' by the per-host limit' : '');
    $this->info($message);
    if($queued || $held_back)
      Log::info('sources:poll '.$message);
  }

}
