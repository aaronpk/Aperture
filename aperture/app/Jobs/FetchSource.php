<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Source;
use App\Services\FeedProcessor, App\Services\PollingSchedule;
use Log;
use Illuminate\Support\Facades\Redis;

/**
 * Fetches a source's URL, processes it if the content changed, and schedules the
 * next check.
 */
class FetchSource implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable;

  public $tries = 1;
  // Must stay below the queue connection's retry_after (90 seconds by default), or a slow
  // fetch would be handed to a second worker while the first is still running
  public $timeout = 75;

  // Don't fetch the same source again within this many seconds
  const MIN_SECONDS_BETWEEN_CHECKS = 30;

  const HTTP_TIMEOUT = 30;

  protected $source_id;

  public function __construct($source_id) {
    $this->source_id = $source_id;
    $this->onQueue(config('aperture.poll.queue'));
  }

  public function sourceId() {
    return $this->source_id;
  }

  public function handle(FeedProcessor $processor) {
    $source = Source::find($this->source_id);
    if(!$source)
      return;

    // Polling state changes shouldn't look like the source itself was edited
    $source->timestamps = false;
    $source->fetch_queued_at = null;

    if(!$source->url || $source->channels()->count() == 0) {
      // Nothing follows this source any more, so there is nothing to fetch it for.
      // sources:poll only selects sources that are in a channel.
      $source->save();
      return;
    }

    if($source->last_checked_at && abs(now()->diffInSeconds($source->last_checked_at)) < self::MIN_SECONDS_BETWEEN_CHECKS) {
      $source->save();
      return;
    }

    $http = app(\p3k\HTTP::class);
    $http->set_user_agent(config('aperture.user_agent'));
    $http->set_timeout(self::HTTP_TIMEOUT);

    $headers = [];
    // Only ask for "not modified" if the content we have was processed successfully
    if($source->content_hash) {
      if($source->http_etag)
        $headers[] = 'If-None-Match: '.$source->http_etag;
      if($source->http_last_modified)
        $headers[] = 'If-Modified-Since: '.$source->http_last_modified;
    }

    $response = $http->get($source->url, $headers);
    $code = (int)$response['code'];
    $source->last_http_status = $code;

    $new_entries = 0;
    $first_import = false;
    $processed = false;

    if($code == 304) {
      $failed = false;
    } elseif(!empty($response['error']) || $code < 200 || $code >= 300 || (string)$response['body'] === '') {
      Log::info('Error fetching source:'.$source->id.' ('.parse_url($source->url, PHP_URL_HOST).') code:'.$code.' '.($response['error'] ?? ''));
      $failed = true;
    } else {
      $content_type = self::header($response, 'Content-Type');
      $content_hash = self::contentHash($response['body'], $content_type);

      if($content_hash == $source->content_hash) {
        $failed = false;
      } else {
        // The first fetch of a new source imports everything already in the feed.
        // That says nothing about how often the feed is updated.
        $first_import = (bool)$source->is_new;

        try {
          $new_entries = $processor->process($source, $response['body']);
        } catch(\Throwable $e) {
          // One feed that can't be processed must not stop it from being rescheduled
          Log::error('Error processing source:'.$source->id.' ('.parse_url($source->url, PHP_URL_HOST).') '.get_class($e).': '.$e->getMessage());
          $new_entries = false;
        }
        // The processor may have saved the source
        $source->timestamps = false;

        if($new_entries === false) {
          // Not a feed, or it couldn't be processed. Don't remember this content, so that
          // it is parsed again next time.
          $failed = true;
          $new_entries = 0;
        } else {
          $failed = false;
          $processed = true;
          $source->content_hash = $content_hash;
          $source->http_etag = substr((string)self::header($response, 'Etag'), 0, 255);
          $source->http_last_modified = substr((string)self::header($response, 'Last-Modified'), 0, 100);
        }
      }
    }

    self::count('fetches');
    if($failed)
      self::count('fetches_failed');
    elseif($processed)
      self::count('fetches_changed');

    $now = now();
    $source->entries_per_day = PollingSchedule::entriesPerDay($source, $first_import ? 0 : $new_entries, $now);
    if($new_entries > 0)
      $source->last_new_entry_at = $now;
    $source->consecutive_failures = $failed ? $source->consecutive_failures + 1 : 0;
    $source->last_checked_at = $now;
    $source->check_interval = PollingSchedule::interval($source, $now);
    $source->next_check_at = PollingSchedule::nextCheckAt($source->check_interval, $now);
    $source->save();
  }

  /**
   * Counters for the /stats/polls graph. Statistics must never get in the way of
   * fetching, so a Redis problem is only logged.
   */
  private static function count($name) {
    try {
      Redis::incr(config('app.url').'::'.$name);
    } catch(\Throwable $e) {
      Log::warning('Could not update the '.$name.' counter: '.$e->getMessage());
    }
  }

  private static function header($response, $name) {
    $value = $response['headers'][$name] ?? null;
    if(is_array($value))
      $value = end($value);
    return is_string($value) ? $value : null;
  }

  /**
   * For HTML, hash only the text, so that things like CSRF tokens and other markup
   * that changes on every request don't count as a change.
   */
  public static function contentHash($body, $content_type) {
    if($content_type && stripos($content_type, 'html') !== false)
      $body = preg_replace('/\s+/', "\n", strtolower(strip_tags($body)));
    return md5($body);
  }

}
