<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\FakeTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\Entry;
use App\Jobs\FetchSource;
use App\Services\FeedProcessor;

class FetchSourceTest extends TestCase
{
  use RefreshDatabase;

  const HEADERS = "Content-Type: text/html; charset=utf-8\r\nETag: \"v1\"\r\nLast-Modified: Sun, 01 Nov 2026 10:00:00 GMT";

  private $source;

  protected function setUp(): void {
    parent::setUp();
    $this->travelTo('2026-11-01 12:00:00');
    $user = $this->createUser();
    $this->source = $this->createSource($user, ['url' => 'https://new.example/', 'created_at' => '2020-01-01 00:00:00', 'updated_at' => '2020-01-01 00:00:00']);
    $this->homeChannel($user)->sources()->attach($this->source->id, ['created_at' => date('Y-m-d H:i:s')]);
  }

  private function feed() {
    return file_get_contents(__DIR__.'/../data/h-feed.html');
  }

  // Replace the canned responses, and forget the requests made so far
  private function respondWith($code, $body='', $headers=self::HEADERS) {
    $this->http = (new FakeTransport())->on('GET', 'https://new.example/', $code, $body, $headers);
  }

  private function fetch() {
    FetchSource::dispatchSync($this->source->id);
    return $this->source->refresh();
  }

  private function secondsUntilNextCheck() {
    return now()->diffInSeconds($this->source->next_check_at);
  }

  public function testFirstFetchAddsEntriesAndSchedulesTheNextCheck() {
    $this->respondWith(200, $this->feed());
    $source = $this->fetch();

    $requests = $this->http->requestsTo('GET', 'https://new.example/');
    $this->assertCount(1, $requests);
    // Nothing stored yet, so the first request is unconditional
    $this->assertEquals([], preg_grep('/^If-/', $requests[0]['headers']));

    $this->assertEquals(2, Entry::where('source_id', $source->id)->count());

    $this->assertEquals(200, $source->last_http_status);
    $this->assertEquals('"v1"', $source->http_etag);
    $this->assertEquals('Sun, 01 Nov 2026 10:00:00 GMT', $source->http_last_modified);
    $this->assertEquals(32, strlen($source->content_hash));
    $this->assertEquals('2026-11-01 12:00:00', $source->last_checked_at);
    $this->assertEquals('2026-11-01 12:00:00', $source->last_new_entry_at);
    $this->assertEqualsWithDelta(2 / 7, $source->entries_per_day, 0.0001);
    $this->assertEquals(0, $source->consecutive_failures);
    $this->assertNull($source->fetch_queued_at);

    // New entry within the last 30 days: checked hourly, give or take 10%
    $this->assertEquals(60, $source->check_interval);
    $this->assertGreaterThanOrEqual(3240, $this->secondsUntilNextCheck());
    $this->assertLessThanOrEqual(3960, $this->secondsUntilNextCheck());

    // Polling state doesn't count as editing the source
    $this->assertEquals('2020-01-01 00:00:00', $source->updated_at->format('Y-m-d H:i:s'));
  }

  public function testFirstImportOfANewSourceDoesNotCountAsActivity() {
    $this->source->is_new = true;
    $this->source->save();

    $this->respondWith(200, $this->feed());
    $source = $this->fetch();

    $this->assertEquals(2, Entry::where('source_id', $source->id)->count());
    $this->assertEquals(0, $source->is_new);
    $this->assertEquals(0, $source->entries_per_day);
    // It still counts as recently active
    $this->assertEquals('2026-11-01 12:00:00', $source->last_new_entry_at);
    $this->assertEquals(60, $source->check_interval);
  }

  public function testNotModifiedResponseIsNotProcessed() {
    $this->respondWith(200, $this->feed());
    $this->fetch();

    $this->travelTo('2026-11-01 13:00:00');
    $this->respondWith(304);
    $this->mock(FeedProcessor::class)->shouldNotReceive('process');
    $source = $this->fetch();

    $headers = $this->http->requestsTo('GET', 'https://new.example/')[0]['headers'];
    $this->assertContains('If-None-Match: "v1"', $headers);
    $this->assertContains('If-Modified-Since: Sun, 01 Nov 2026 10:00:00 GMT', $headers);

    $this->assertEquals(304, $source->last_http_status);
    $this->assertEquals(0, $source->consecutive_failures);
    $this->assertEquals('2026-11-01 13:00:00', $source->last_checked_at);
    $this->assertEquals('2026-11-01 12:00:00', $source->last_new_entry_at);
    $this->assertEquals('"v1"', $source->http_etag);
  }

  public function testHtmlThatOnlyDiffersInMarkupIsNotProcessed() {
    $this->respondWith(200, $this->feed());
    $hash = $this->fetch()->content_hash;

    $this->travelTo('2026-11-01 13:00:00');
    $this->respondWith(200, str_replace('<body>', '<body data-csrf="'.bin2hex(random_bytes(8)).'">', $this->feed()));
    $this->mock(FeedProcessor::class)->shouldNotReceive('process');
    $source = $this->fetch();

    $this->assertEquals($hash, $source->content_hash);
    $this->assertEquals(0, $source->consecutive_failures);
  }

  public function testOtherContentTypesAreComparedExactly() {
    $this->assertEquals(FetchSource::contentHash('<p>Hi</p>', 'text/html'), FetchSource::contentHash('<p class="x">hi</p>', 'text/html; charset=utf-8'));
    $this->assertNotEquals(FetchSource::contentHash('<p>Hi</p>', 'text/xml'), FetchSource::contentHash('<p class="x">hi</p>', 'text/xml'));
    $this->assertNotEquals(FetchSource::contentHash('{"a":1}', null), FetchSource::contentHash('{"a":2}', null));
  }

  public function testChangedContentIsProcessed() {
    $onePost = preg_replace('/<div class="h-entry">\s*<a class="u-url" href="https:\/\/new.example\/2">.*?<\/div>/s', '', $this->feed());
    $this->respondWith(200, $onePost);
    $source = $this->fetch();
    $this->assertEquals(1, Entry::where('source_id', $source->id)->count());
    $hash = $source->content_hash;

    $this->travelTo('2026-11-01 13:00:00');
    $this->respondWith(200, $this->feed(), "Content-Type: text/html\r\nETag: \"v2\"");
    $source = $this->fetch();

    $this->assertEquals(2, Entry::where('source_id', $source->id)->count());
    $this->assertNotEquals($hash, $source->content_hash);
    $this->assertEquals('"v2"', $source->http_etag);
    $this->assertEquals('', $source->http_last_modified);
    $this->assertEquals('2026-11-01 13:00:00', $source->last_new_entry_at);
  }

  public function testFailuresBackOffAndASuccessResetsThem() {
    $this->respondWith(200, $this->feed());
    $hash = $this->fetch()->content_hash;

    $this->respondWith(500, 'oops');
    for($i = 1; $i <= 5; $i++) {
      $this->travelTo(now()->addHour());
      $source = $this->fetch();
      $this->assertEquals($i, $source->consecutive_failures);
      $this->assertEquals(500, $source->last_http_status);
      $this->assertEquals($i < 5 ? 60 : 360, $source->check_interval);
    }
    // What we know about the last good copy is kept
    $this->assertEquals($hash, $source->content_hash);
    $this->assertEquals(2, Entry::where('source_id', $source->id)->count());

    $this->travelTo(now()->addHours(6));
    $this->respondWith(304);
    $source = $this->fetch();
    $this->assertEquals(0, $source->consecutive_failures);
    $this->assertEquals(60, $source->check_interval);
  }

  public function testEmptyBodyIsAFailure() {
    $this->respondWith(200, '');
    $this->assertEquals(1, $this->fetch()->consecutive_failures);
  }

  public function testContentThatIsNotAFeedIsAFailureAndIsParsedAgainNextTime() {
    $this->respondWith(200, '{"not":"a feed"}', 'Content-Type: application/json');
    $source = $this->fetch();
    $this->assertEquals(1, $source->consecutive_failures);
    $this->assertEquals('', $source->content_hash);

    $this->travelTo(now()->addHour());
    $source = $this->fetch();
    $this->assertEquals(2, $source->consecutive_failures);
    // No conditional request, because nothing was successfully processed
    $this->assertEquals([], preg_grep('/^If-/', $this->http->requests[1]['headers']));
  }

  public function testErrorWhileProcessingIsAFailureAndTheSourceIsStillRescheduled() {
    $this->source->fetch_queued_at = now();
    $this->source->save();

    $this->respondWith(200, $this->feed());
    $this->mock(FeedProcessor::class)->shouldReceive('process')->once()->andThrow(new \ErrorException('Undefined array key "items"'));
    $source = $this->fetch();

    $this->assertEquals(1, $source->consecutive_failures);
    $this->assertEquals('', $source->content_hash);
    $this->assertNull($source->fetch_queued_at);
    $this->assertEquals('2026-11-01 12:00:00', $source->last_checked_at);
    $this->assertNotNull($source->next_check_at);
  }

  public function testSourceThatIsNotInAChannelIsNotFetched() {
    $this->source->channels()->detach();
    $this->source->fetch_queued_at = now();
    $this->source->save();

    $this->respondWith(200, $this->feed());
    $source = $this->fetch();

    $this->assertCount(0, $this->http->requests);
    $this->assertNull($source->fetch_queued_at);
    $this->assertNull($source->last_checked_at);
  }

  public function testSourceCheckedSecondsAgoIsNotFetchedAgain() {
    $this->respondWith(200, $this->feed());
    $this->fetch();
    $this->travelTo(now()->addSeconds(20));
    $this->fetch();
    $this->assertCount(1, $this->http->requests);

    $this->travelTo(now()->addSeconds(20));
    $this->fetch();
    $this->assertCount(2, $this->http->requests);
  }

  public function testFetchesGoOnTheFeedsQueue() {
    Queue::fake();
    FetchSource::dispatch($this->source->id);
    Queue::assertPushedOn('feeds', FetchSource::class);
  }
}
