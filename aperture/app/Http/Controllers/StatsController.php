<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log;
use App\Models\User, App\Models\Source, App\Models\Channel, App\Models\Entry, App\Models\Media;
use Illuminate\Support\Facades\Redis;
use App\Services\PollingSchedule;
use Illuminate\Routing\Controllers\HasMiddleware;

class StatsController extends Controller implements HasMiddleware
{

  public static function middleware(): array {
    return ['munin'];
  }

  private function _text($text) {
    return response($text)->header('Content-type', 'text/plain');
  }

  // Every statistic that has a /stats/{name} route. The Munin plugin in munin/aperture_
  // reads this list from /stats to know which graphs exist.
  const GRAPHS = ['users', 'new_entries', 'entries', 'entries_size', 'sources', 'media_size', 'polling_tiers', 'polls', 'polling_failures', 'polling_backlog'];

  public function index() {
    return $this->_text(implode("\n", self::GRAPHS)."\n");
  }

  public function users() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Users
graph_info Total number of Aperture user accounts
graph_vlabel Users
graph_category aperture
graph_args --lower-limit 0
graph_scale yes

users.label Users
users.type GAUGE
users.min 0
";
    } else {
      $users = User::count();
      $response = 'users.value '.$users;
    }
    return $this->_text($response);
  }

  public function new_entries() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture New Entries
graph_info The rate of new entries being added
graph_vlabel Entries per minute
graph_category aperture
graph_args --lower-limit 0
graph_scale yes
graph_period minute

new_entries.label Entries added per minute
new_entries.type DERIVE
new_entries.min 0
";
    } else {
      $entries = DB::SELECT('SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_schema="'.DB::getDatabaseName().'" AND table_name="entries"');
      $response = 'new_entries.value '.$entries[0]->AUTO_INCREMENT;
    }

    return $this->_text($response);
  }

  public function entries() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Entries
graph_info The total entries currently stored
graph_vlabel Entries
graph_category aperture
graph_args --lower-limit 0
graph_scale yes

entries.label Entries
entries.type GAUGE
entries.min 0
";
    } else {
      $entries = Redis::get(config('app.url').'::entries');
      // The counter doesn't exist until data:update_entry_count has run. "U" is
      // Munin's way of saying the value is unknown.
      $response = 'entries.value '.(is_numeric($entries) ? $entries : 'U');
    }

    return $this->_text($response);
  }

  public function entries_size() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Entries
graph_info The size on disk of entries
graph_vlabel Entries
graph_category aperture
graph_args --lower-limit 0 --base 1024
graph_scale yes

size.label Size on Disk
size.type GAUGE
size.min 0
";
    } else {
      $size = DB::SELECT('SELECT data_length - data_free AS bytes FROM information_schema.tables WHERE table_schema="aperture" AND table_name="entries"');
      $response = "size.value ".$size[0]->bytes;
    }
    return $this->_text($response);
  }

  public function sources() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Sources
graph_info Total number of active sources in Aperture
graph_vlabel Sources
graph_category aperture
graph_args --lower-limit 0
graph_scale yes

sources.label Sources
sources.type GAUGE
sources.min 0
";
    } else {
      $sources = DB::SELECT('SELECT COUNT(*) AS num FROM (SELECT COUNT(*) FROM `sources` INNER JOIN `channel_source` ON (`channel_source`.`source_id` = `sources`.`id`) GROUP BY sources.id) AS tmp');
      $response = 'sources.value '.$sources[0]->num;
    }
    return $this->_text($response);
  }

  public function media_size() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Media
graph_info Total size of media stored in Aperture
graph_vlabel Bytes
graph_category aperture
graph_args --lower-limit 0 --base 1024
graph_scale yes

known.label Bytes Known
known.type GAUGE
known.min 0
disk.label Bytes on Disk
disk.type GAUGE
disk.min 0
";
    } else {
      $known = Media::sum('bytes');
      $du = shell_exec('du -sk '.escapeshellarg(storage_path('app/media')).' 2>/dev/null');
      if(preg_match('/^(\d+)/', (string)$du, $match)) {
        $disk = ((int)$match[1])*1024;
      } else {
        // "U" is Munin's way of saying the value is unknown
        $disk = 'U';
      }
      $response = 'known.value '.$known."\n";
      $response .= 'disk.value '.$disk;
    }
    return $this->_text($response);
  }

  // Sources that sources:poll checks: they have a URL, are in at least one channel, and
  // haven't been stopped for failing for too long
  private function _polledSources() {
    return Source::where('url', '!=', '')
      ->whereNull('stopped_at')
      ->whereExists(function($q) {
        $q->selectRaw('1')->from('channel_source')->whereColumn('channel_source.source_id', 'sources.id');
      });
  }

  public function polling_tiers() {
    $tiers = [
      PollingSchedule::FAST => ['fast', 'Every 5 minutes'],
      PollingSchedule::FREQUENT => ['frequent', 'Every 15 minutes'],
      PollingSchedule::ACTIVE => ['active', 'Hourly'],
      PollingSchedule::QUIET => ['quiet', 'Every 6 hours'],
      PollingSchedule::DORMANT => ['dormant', 'Daily'],
    ];

    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Polling Tiers
graph_info How often sources are checked for new entries
graph_vlabel Sources
graph_category aperture
graph_args --lower-limit 0
graph_scale no

";
      $first = true;
      foreach($tiers as $tier) {
        $response .= $tier[0].'.label '.$tier[1]."\n";
        $response .= $tier[0].'.draw '.($first ? 'AREA' : 'STACK')."\n";
        $response .= $tier[0].".type GAUGE\n";
        $response .= $tier[0].".min 0\n";
        $first = false;
      }
    } else {
      $counts = $this->_polledSources()->selectRaw('check_interval, COUNT(*) AS num')
        ->groupBy('check_interval')->pluck('num', 'check_interval');
      $lines = [];
      foreach($tiers as $interval => $tier) {
        $lines[] = $tier[0].'.value '.($counts[$interval] ?? 0);
      }
      $response = implode("\n", $lines);
    }
    return $this->_text($response);
  }

  public function polls() {
    $fields = [
      'fetches' => 'Sources fetched',
      'fetches_changed' => 'With changed content',
      'fetches_failed' => 'Failed',
    ];

    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Polls
graph_info The rate at which sources are fetched
graph_vlabel Fetches per minute
graph_category aperture
graph_args --lower-limit 0
graph_scale no
graph_period minute

";
      foreach($fields as $name => $label) {
        $response .= $name.'.label '.$label."\n";
        $response .= $name.".type DERIVE\n";
        $response .= $name.".min 0\n";
      }
    } else {
      $lines = [];
      foreach($fields as $name => $label) {
        // Counted by the FetchSource job. A counter that has never been touched is zero.
        $lines[] = $name.'.value '.(int)Redis::get(config('app.url').'::'.$name);
      }
      $response = implode("\n", $lines);
    }
    return $this->_text($response);
  }

  public function polling_failures() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Failing Sources
graph_info Sources whose most recent fetches failed
graph_vlabel Sources
graph_category aperture
graph_args --lower-limit 0
graph_scale no

failing.label Failed at least once
failing.type GAUGE
failing.min 0
slowed.label Slowed to every 6 hours (".PollingSchedule::FAILURES_BEFORE_QUIET."+ failures)
slowed.type GAUGE
slowed.min 0
daily.label Slowed to daily (".PollingSchedule::FAILURES_BEFORE_DORMANT."+ failures)
daily.type GAUGE
daily.min 0
stopped.label Stopped after ".PollingSchedule::STOP_AFTER_DAYS." days of failures
stopped.type GAUGE
stopped.min 0
";
    } else {
      $row = $this->_polledSources()->selectRaw('
        COALESCE(SUM(consecutive_failures >= 1), 0) AS failing,
        COALESCE(SUM(consecutive_failures >= ?), 0) AS slowed,
        COALESCE(SUM(consecutive_failures >= ?), 0) AS daily
      ', [PollingSchedule::FAILURES_BEFORE_QUIET, PollingSchedule::FAILURES_BEFORE_DORMANT])->first();
      // Stopped sources aren't polled, so they are counted separately from the rest
      $stopped = Source::where('url', '!=', '')->whereNotNull('stopped_at')
        ->whereExists(function($q) {
          $q->selectRaw('1')->from('channel_source')->whereColumn('channel_source.source_id', 'sources.id');
        })->count();
      $response = 'failing.value '.(int)$row->failing."\n"
        .'slowed.value '.(int)$row->slowed."\n"
        .'daily.value '.(int)$row->daily."\n"
        .'stopped.value '.$stopped;
    }
    return $this->_text($response);
  }

  public function polling_backlog() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Polling Backlog
graph_info Sources that are past their check time. This grows if the scheduler or the feeds queue workers stop.
graph_vlabel Sources
graph_category aperture
graph_args --lower-limit 0
graph_scale no

overdue.label Overdue by more than 5 minutes
overdue.type GAUGE
overdue.min 0
queued.label Waiting in the queue
queued.type GAUGE
queued.min 0
";
    } else {
      $now = now();
      $row = $this->_polledSources()->selectRaw('
        COALESCE(SUM(next_check_at IS NULL OR next_check_at < ?), 0) AS overdue,
        COALESCE(SUM(fetch_queued_at IS NOT NULL), 0) AS queued
      ', [$now->copy()->subMinutes(5)])->first();
      $response = 'overdue.value '.(int)$row->overdue."\n"
        .'queued.value '.(int)$row->queued;
    }
    return $this->_text($response);
  }


}
