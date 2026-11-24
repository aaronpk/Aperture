<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\FakeTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use App\Jobs\FetchSource;

class PollingStatsTest extends TestCase
{
  use RefreshDatabase;

  private $user;
  private $channel;

  protected function setUp(): void {
    parent::setUp();
    $this->travelTo('2026-11-01 12:00:00');
    config(['aperture.munin_token' => 'munin']);
    $this->user = $this->createUser();
    $this->channel = $this->homeChannel($this->user);
    foreach(['fetches', 'fetches_changed', 'fetches_failed'] as $counter)
      Redis::del(config('app.url').'::'.$counter);
  }

  private function source(array $attrs=[], $inChannel=true) {
    $source = $this->createSource($this->user, array_merge(['next_check_at' => '2026-11-01 12:30:00'], $attrs));
    if($inChannel)
      $this->channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);
    return $source;
  }

  private function stat($name) {
    $response = $this->get('/stats/'.$name.'?token=munin');
    $response->assertStatus(200);
    $values = [];
    foreach(explode("\n", trim($response->getContent())) as $line) {
      list($field, $value) = explode(' ', $line);
      $values[substr($field, 0, -6)] = $value;
    }
    return $values;
  }

  public function testPollingTiersCountsFollowedSourcesByInterval() {
    foreach([5, 15, 15, 60, 60, 60, 1440] as $interval)
      $this->source(['check_interval' => $interval]);
    // Not counted: in no channel, or nothing to fetch
    $this->source(['check_interval' => 5], false);
    $this->source(['check_interval' => 5, 'url' => '', 'format' => 'apikey']);

    $this->source(['check_interval' => 5, 'stopped_at' => '2026-10-30 00:00:00']);

    $this->assertEquals(['fast' => 1, 'frequent' => 2, 'active' => 3, 'quiet' => 0, 'dormant' => 1], $this->stat('polling_tiers'));

    $config = $this->get('/stats/polling_tiers?token=munin&mode=config')->getContent();
    $this->assertStringContainsString("fast.label Every 5 minutes\nfast.draw AREA\n", $config);
    $this->assertStringContainsString("dormant.label Daily\ndormant.draw STACK\n", $config);
  }

  public function testPollingFailuresCountsSourcesByHowLongTheyHaveFailed() {
    foreach([0, 0, 1, 4, 5, 29, 30, 200] as $failures)
      $this->source(['consecutive_failures' => $failures]);
    $this->source(['consecutive_failures' => 50], false);
    // Stopped sources are no longer polled, so they are only counted as stopped
    $this->source(['consecutive_failures' => 40, 'stopped_at' => '2026-10-30 00:00:00']);
    $this->source(['consecutive_failures' => 40, 'stopped_at' => '2026-10-30 00:00:00'], false);

    $this->assertEquals(['failing' => 6, 'slowed' => 4, 'daily' => 2, 'stopped' => 1], $this->stat('polling_failures'));
  }

  public function testPollingBacklogCountsOverdueAndQueuedSources() {
    $this->source(['next_check_at' => '2026-11-01 12:30:00']);                                        // not due
    $this->source(['next_check_at' => '2026-11-01 11:58:00']);                                        // due, within the grace period
    $this->source(['next_check_at' => '2026-11-01 11:54:59']);                                        // overdue
    $this->source(['next_check_at' => null]);                                                         // never scheduled
    $this->source(['next_check_at' => '2026-11-01 11:00:00', 'fetch_queued_at' => '2026-11-01 11:59:00']); // overdue and queued
    $this->source(['next_check_at' => '2026-11-01 09:00:00'], false);                                 // not followed

    $this->source(['next_check_at' => null, 'stopped_at' => '2026-10-30 00:00:00']);                  // stopped

    $this->assertEquals(['overdue' => 3, 'queued' => 1], $this->stat('polling_backlog'));
  }

  public function testEmptyDatabaseReportsZeros() {
    $this->assertEquals(['fast' => 0, 'frequent' => 0, 'active' => 0, 'quiet' => 0, 'dormant' => 0], $this->stat('polling_tiers'));
    $this->assertEquals(['failing' => 0, 'slowed' => 0, 'daily' => 0, 'stopped' => 0], $this->stat('polling_failures'));
    $this->assertEquals(['overdue' => 0, 'queued' => 0], $this->stat('polling_backlog'));
    $this->assertEquals(['fetches' => 0, 'fetches_changed' => 0, 'fetches_failed' => 0], $this->stat('polls'));
  }

  public function testPollsCountsEveryFetchAndItsOutcome() {
    $feed = file_get_contents(__DIR__.'/../data/h-feed.html');
    $good = $this->source(['url' => 'https://new.example/']);
    $broken = $this->source(['url' => 'https://broken.example/']);

    $this->http->on('GET', 'https://new.example/', 200, $feed, "Content-Type: text/html\r\nETag: \"v1\"");
    $this->http->on('GET', 'https://broken.example/', 500, 'oops');

    FetchSource::dispatchSync($good->id);      // changed
    FetchSource::dispatchSync($broken->id);    // failed
    $this->assertEquals(['fetches' => 2, 'fetches_changed' => 1, 'fetches_failed' => 1], $this->stat('polls'));

    // Fetched again with nothing new: counted as a fetch, but not as changed or failed
    $this->travelTo('2026-11-01 13:00:00');
    FetchSource::dispatchSync($good->id);
    $this->http = (new FakeTransport())->on('GET', 'https://new.example/', 304);
    $this->travelTo('2026-11-01 14:00:00');
    FetchSource::dispatchSync($good->id);
    $this->assertEquals(['fetches' => 4, 'fetches_changed' => 1, 'fetches_failed' => 1], $this->stat('polls'));

    // A fetch that is skipped isn't counted
    FetchSource::dispatchSync($good->id);
    $this->assertEquals(4, $this->stat('polls')['fetches']);

    $config = $this->get('/stats/polls?token=munin&mode=config')->getContent();
    $this->assertStringContainsString("graph_period minute\n", $config);
    $this->assertStringContainsString("fetches.type DERIVE\n", $config);
  }

  public function testFetchingStillWorksWhenTheCounterCannotBeUpdated() {
    $source = $this->source(['url' => 'https://new.example/']);
    $this->http->on('GET', 'https://new.example/', 200, file_get_contents(__DIR__.'/../data/h-feed.html'));
    // Only the polling counters fail here. Creating an entry has its own counter.
    Redis::shouldReceive('incr')->andReturnUsing(function($key) {
      if(str_contains($key, '::fetches'))
        throw new \RuntimeException('Connection refused');
      return 1;
    });

    FetchSource::dispatchSync($source->id);

    $this->assertNotNull($source->fresh()->last_checked_at);
    $this->assertEquals(0, $source->fresh()->consecutive_failures);
  }
}
