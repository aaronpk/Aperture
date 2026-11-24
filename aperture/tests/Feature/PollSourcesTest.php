<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Carbon;
use App\Models\Source;
use App\Jobs\FetchSource;
use App\Services\PollingSchedule;
use DB;

class PollSourcesTest extends TestCase
{
  use RefreshDatabase;

  private $user;
  private $channel;

  protected function setUp(): void {
    parent::setUp();
    $this->travelTo('2026-11-01 12:00:00');
    $this->user = $this->createUser();
    $this->channel = $this->homeChannel($this->user);
    Queue::fake();
  }

  private function source($url, array $attrs=[], $inChannel=true) {
    $source = $this->createSource($this->user, array_merge(['url' => $url, 'next_check_at' => '2026-11-01 11:59:00'], $attrs));
    if($inChannel)
      $this->channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);
    return $source;
  }

  private function queuedIds() {
    return Queue::pushed(FetchSource::class)->map(function($job) { return $job->sourceId(); })->all();
  }

  public function testQueuesOnlySourcesThatAreDueAndFollowed() {
    $due = $this->source('https://a.example/');
    $never_checked = $this->source('https://b.example/', ['next_check_at' => null]);
    $not_due = $this->source('https://c.example/', ['next_check_at' => '2026-11-01 12:30:00']);
    $not_followed = $this->source('https://d.example/', [], false);
    $api_key = $this->source('', ['format' => 'apikey']);
    $already_queued = $this->source('https://e.example/', ['fetch_queued_at' => '2026-11-01 11:30:00']);
    $lost_in_queue = $this->source('https://f.example/', ['fetch_queued_at' => '2026-11-01 10:59:00']);

    $this->artisan('sources:poll')->expectsOutput('Queued 3 sources')->assertExitCode(0);

    // Never-checked sources go first, then the most overdue
    $this->assertEquals([$never_checked->id, $due->id, $lost_in_queue->id], $this->queuedIds());

    $this->assertEquals('2026-11-01 12:00:00', $due->fresh()->fetch_queued_at);
    $this->assertNull($not_due->fresh()->fetch_queued_at);

    // A source that is waiting in the queue isn't queued a second time
    $this->travelTo('2026-11-01 12:01:00');
    $this->artisan('sources:poll')->expectsOutput('Queued 0 sources');
    $this->assertCount(3, $this->queuedIds());
  }

  public function testLimitsHowManySourcesAreQueuedPerRun() {
    config(['aperture.poll.max_per_run' => 2]);
    $third = $this->source('https://a.example/', ['next_check_at' => '2026-11-01 11:50:00']);
    $first = $this->source('https://b.example/', ['next_check_at' => '2026-11-01 09:00:00']);
    $second = $this->source('https://c.example/', ['next_check_at' => '2026-11-01 11:00:00']);

    $this->artisan('sources:poll');
    $this->assertEquals([$first->id, $second->id], $this->queuedIds());

    $this->artisan('sources:poll');
    $this->assertEquals([$first->id, $second->id, $third->id], $this->queuedIds());
  }

  public function testLimitsHowManySourcesAreQueuedPerHost() {
    config(['aperture.poll.max_per_host' => 2]);
    $a = $this->source('https://www.youtube.example/feeds/1', ['next_check_at' => '2026-11-01 11:00:00']);
    $b = $this->source('https://WWW.youtube.example/feeds/2', ['next_check_at' => '2026-11-01 11:01:00']);
    $c = $this->source('https://www.youtube.example/feeds/3', ['next_check_at' => '2026-11-01 11:02:00']);
    $other = $this->source('https://blog.example/', ['next_check_at' => '2026-11-01 11:03:00']);

    $this->artisan('sources:poll')->expectsOutput('Queued 3 sources, held back 1 by the per-host limit');
    $this->assertEquals([$a->id, $b->id, $other->id], $this->queuedIds());

    // The one that was held back is still due, and goes out on the next run
    $this->assertNull($c->fresh()->fetch_queued_at);
    $this->artisan('sources:poll')->expectsOutput('Queued 1 sources');
    $this->assertEquals($c->id, $this->queuedIds()[3]);
  }

  public function testPollingDoesNotTouchUpdatedAt() {
    $source = $this->source('https://a.example/', ['updated_at' => '2020-01-01 00:00:00']);
    $this->artisan('sources:poll');
    $this->assertEquals('2020-01-01 00:00:00', $source->fresh()->updated_at->format('Y-m-d H:i:s'));
  }

  public function testPollIsScheduledEveryMinute() {
    $this->artisan('schedule:list')->expectsOutputToContain('sources:poll');
  }

  public function testBackfillGivesExistingSourcesATierFromTheirEntries() {
    $now = Carbon::parse('2026-11-01 12:00:00');
    $old = ['created_at' => '2020-01-01 00:00:00', 'next_check_at' => null];

    $make = function($url, $entryDates, $attrs=[]) use($old) {
      $source = $this->source($url, array_merge($old, $attrs));
      $rows = [];
      foreach($entryDates as $i => $date) {
        $rows[] = ['source_id' => $source->id, 'unique' => 'e'.$i, 'data' => '{}', 'created_at' => $date, 'updated_at' => $date];
      }
      foreach(array_chunk($rows, 500) as $chunk)
        DB::table('entries')->insert($chunk);
      return $source;
    };

    $fast = $make('https://fast.example/', array_fill(0, 70, '2026-10-30 08:00:00'));         // 10 a day
    $frequent = $make('https://frequent.example/', array_fill(0, 7, '2026-10-28 08:00:00'));   // 1 a day
    $active = $make('https://active.example/', ['2026-10-10 08:00:00']);
    $quiet = $make('https://quiet.example/', ['2026-03-01 08:00:00']);
    $dormant = $make('https://dormant.example/', ['2024-03-01 08:00:00']);
    $empty = $make('https://empty.example/', []);
    $just_added = $make('https://added.example/', [], ['created_at' => '2026-10-25 00:00:00']);
    $imported = $make('https://imported.example/', array_fill(0, 100, '2026-10-29 08:00:00'), ['created_at' => '2026-10-29 07:59:00']);
    $api_key = $make('', ['2026-10-30 08:00:00'], ['format' => 'apikey']);

    PollingSchedule::backfill($now);

    $interval = function($source) { return $source->fresh()->check_interval; };
    $this->assertEquals(5, $interval($fast));
    $this->assertEquals(15, $interval($frequent));
    $this->assertEquals(60, $interval($active));
    $this->assertEquals(360, $interval($quiet));
    $this->assertEquals(1440, $interval($dormant));
    $this->assertEquals(1440, $interval($empty));
    $this->assertEquals(60, $interval($just_added));
    // 100 entries imported when the source was added 3 days ago aren't a posting rate
    $this->assertEquals(60, $interval($imported));
    $this->assertEquals(0, $imported->fresh()->entries_per_day);

    $this->assertEqualsWithDelta(10, $fast->fresh()->entries_per_day, 0.001);
    $this->assertEquals('2026-03-01 08:00:00', $quiet->fresh()->last_new_entry_at);
    $this->assertNull($empty->fresh()->last_new_entry_at);

    // The backfill agrees with the rule used after every fetch
    foreach([$fast, $frequent, $active, $quiet, $dormant, $empty, $just_added, $imported] as $source) {
      $source = $source->fresh();
      $this->assertEquals(PollingSchedule::interval($source, $now), $source->check_interval, $source->url);
      // First checks are spread across the source's own interval
      $next = Carbon::parse($source->next_check_at);
      $this->assertTrue($next >= $now && $next <= $now->copy()->addMinutes($source->check_interval), $source->url);
    }

    // Sources without a URL are never polled, so they are left alone
    $this->assertNull($api_key->fresh()->next_check_at);
  }
}
