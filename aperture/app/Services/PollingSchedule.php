<?php
namespace App\Services;

use App\Models\Source;
use Illuminate\Support\Carbon;
use DB;

/**
 * Decides how often a source is polled.
 *
 * The tier follows how often a source produces new entries, not whether the fetched
 * content changed: many feeds change on every request without containing anything new.
 */
class PollingSchedule
{
  // Polling intervals in minutes
  const FAST = 5;        // 10 or more new entries a day
  const FREQUENT = 15;   // 1 or more new entries a day
  const ACTIVE = 60;     // a new entry in the last 30 days, or a recently added source
  const QUIET = 360;     // a new entry in the last year
  const DORMANT = 1440;  // nothing for over a year, or never

  const FAST_RATE = 10;
  const FREQUENT_RATE = 1;
  const ACTIVE_DAYS = 30;
  const QUIET_DAYS = 365;

  // entries_per_day is a moving average over this many days
  const AVERAGE_DAYS = 7;

  // Sources that keep failing are checked less often
  const FAILURES_BEFORE_QUIET = 5;
  const FAILURES_BEFORE_DORMANT = 30;

  public static function interval(Source $source, ?Carbon $now=null) {
    $now = $now ?: now();

    if($source->consecutive_failures >= self::FAILURES_BEFORE_DORMANT)
      return self::DORMANT;

    $interval = self::intervalFromActivity($source, $now);

    if($source->consecutive_failures >= self::FAILURES_BEFORE_QUIET)
      $interval = max($interval, self::QUIET);

    return $interval;
  }

  private static function intervalFromActivity(Source $source, Carbon $now) {
    if($source->entries_per_day >= self::FAST_RATE)
      return self::FAST;

    if($source->entries_per_day >= self::FREQUENT_RATE)
      return self::FREQUENT;

    $activeSince = $now->copy()->subDays(self::ACTIVE_DAYS);
    $last = $source->last_new_entry_at ? Carbon::parse($source->last_new_entry_at) : null;

    if($last && $last >= $activeSince)
      return self::ACTIVE;

    // A source that was only just added hasn't had a chance to show how active it is
    if(!$source->created_at || $source->created_at >= $activeSince)
      return self::ACTIVE;

    if($last && $last >= $now->copy()->subDays(self::QUIET_DAYS))
      return self::QUIET;

    return self::DORMANT;
  }

  /**
   * The moving average of new entries per day after a check that found $newEntries.
   */
  public static function entriesPerDay(Source $source, $newEntries, ?Carbon $now=null) {
    $now = $now ?: now();

    $elapsedDays = 0;
    if($source->last_checked_at)
      $elapsedDays = max(0, Carbon::parse($source->last_checked_at)->diffInSeconds($now, false)) / 86400;

    return $source->entries_per_day * exp(-$elapsedDays / self::AVERAGE_DAYS)
      + $newEntries / self::AVERAGE_DAYS;
  }

  /**
   * When to check next: the interval, give or take 10% so that sources don't bunch up.
   */
  public static function nextCheckAt($interval, ?Carbon $now=null) {
    $now = $now ?: now();
    $seconds = $interval * 60;
    $jitter = (int)round($seconds * 0.1);
    return $now->copy()->addSeconds($seconds + random_int(-$jitter, $jitter));
  }

  /**
   * Gives every source with a URL a starting tier based on its existing entries, and
   * spreads the first checks randomly across each source's interval.
   */
  public static function backfill(Carbon $now) {
    $ts = $now->format('Y-m-d H:i:s');

    DB::update('
      UPDATE sources s
      LEFT JOIN (
        SELECT source_id,
          MAX(created_at) AS last_at,
          SUM(created_at >= DATE_SUB(?, INTERVAL '.self::AVERAGE_DAYS.' DAY)) / '.self::AVERAGE_DAYS.' AS rate
        FROM entries
        GROUP BY source_id
      ) e ON e.source_id = s.id
      SET s.last_new_entry_at = e.last_at,
        -- The entries of a source that was only just added are mostly its first import
        s.entries_per_day = IF(s.created_at >= DATE_SUB(?, INTERVAL '.self::AVERAGE_DAYS.' DAY), 0, COALESCE(e.rate, 0))
      WHERE s.url != ""
    ', [$ts, $ts]);

    DB::update('
      UPDATE sources
      SET check_interval = CASE
          WHEN entries_per_day >= '.self::FAST_RATE.' THEN '.self::FAST.'
          WHEN entries_per_day >= '.self::FREQUENT_RATE.' THEN '.self::FREQUENT.'
          WHEN last_new_entry_at >= DATE_SUB(?, INTERVAL '.self::ACTIVE_DAYS.' DAY) THEN '.self::ACTIVE.'
          WHEN created_at IS NULL OR created_at >= DATE_SUB(?, INTERVAL '.self::ACTIVE_DAYS.' DAY) THEN '.self::ACTIVE.'
          WHEN last_new_entry_at >= DATE_SUB(?, INTERVAL '.self::QUIET_DAYS.' DAY) THEN '.self::QUIET.'
          ELSE '.self::DORMANT.'
        END
      WHERE url != ""
    ', [$ts, $ts, $ts]);

    DB::update('
      UPDATE sources
      SET next_check_at = DATE_ADD(?, INTERVAL FLOOR(RAND() * check_interval * 60) SECOND)
      WHERE url != ""
    ', [$ts]);
  }
}
