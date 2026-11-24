<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\FakeTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\Entry;
use App\Jobs\FetchSource;

/**
 * A source whose every fetch fails for 30 days stops being polled. Following it again,
 * or pressing "Try again" in the channel's settings, starts it up again.
 */
class StoppedSourcesTest extends TestCase
{
  use RefreshDatabase;

  private $user;
  private $channel;
  private $source;

  protected function setUp(): void {
    parent::setUp();
    $this->travelTo('2026-11-01 12:00:00');
    $this->user = $this->createUser();
    $this->channel = $this->homeChannel($this->user);
    $this->source = $this->createSource($this->user, ['url' => 'https://new.example/', 'created_at' => '2020-01-01 00:00:00']);
    $this->channel->sources()->attach($this->source->id, ['created_at' => date('Y-m-d H:i:s')]);
  }

  private function feed() {
    return file_get_contents(__DIR__.'/../data/h-feed.html');
  }

  private function respondWith($code, $body='') {
    $this->http = (new FakeTransport())->on('GET', 'https://new.example/', $code, $body);
  }

  private function fetch() {
    FetchSource::dispatchSync($this->source->id);
    return $this->source->refresh();
  }

  private function stop() {
    $this->source->forceFill([
      'stopped_at' => '2026-11-01 12:00:00',
      'failing_since' => '2026-10-01 12:00:00',
      'consecutive_failures' => 45,
      'last_http_status' => 404,
      'next_check_at' => null,
    ])->save();
  }

  public function testFailingSinceIsWhenTheCurrentRunOfFailuresBegan() {
    $this->respondWith(500);
    $this->assertEquals('2026-11-01 12:00:00', $this->fetch()->failing_since);

    $this->travelTo('2026-11-02 12:00:00');
    $this->assertEquals('2026-11-01 12:00:00', $this->fetch()->failing_since);

    // A success ends the run
    $this->travelTo('2026-11-03 12:00:00');
    $this->respondWith(200, $this->feed());
    $this->assertNull($this->fetch()->failing_since);

    // The next failure starts a new one
    $this->travelTo('2026-11-04 12:00:00');
    $this->respondWith(500);
    $this->assertEquals('2026-11-04 12:00:00', $this->fetch()->failing_since);
  }

  public function testSourceIsStoppedAfterThirtyDaysOfFailures() {
    $this->respondWith(404);
    $this->fetch();

    // Still failing on day 29: slowed down, but not stopped
    $this->travelTo('2026-11-30 11:00:00');
    $source = $this->fetch();
    $this->assertNull($source->stopped_at);
    $this->assertNotNull($source->next_check_at);

    // The first failure after 30 full days stops it
    $this->travelTo('2026-12-01 12:00:00');
    $source = $this->fetch();
    $this->assertEquals('2026-12-01 12:00:00', $source->stopped_at);
    $this->assertNull($source->next_check_at);
    $this->assertEquals('2026-11-01 12:00:00', $source->failing_since);
    $this->assertEquals(404, $source->last_http_status);
  }

  public function testOneSuccessInThirtyDaysKeepsASourceGoing() {
    $this->respondWith(500);
    $this->fetch();

    $this->travelTo('2026-11-20 12:00:00');
    $this->respondWith(200, $this->feed());
    $this->fetch();

    $this->travelTo('2026-12-05 12:00:00');
    $this->respondWith(500);
    $source = $this->fetch();

    $this->assertNull($source->stopped_at);
    $this->assertEquals('2026-12-05 12:00:00', $source->failing_since);
  }

  public function testStoppedSourceIsNotPolledOrFetched() {
    $this->stop();
    $this->respondWith(200, $this->feed());

    Queue::fake();
    $this->artisan('sources:poll')->expectsOutput('Queued 0 sources');
    Queue::assertNothingPushed();

    // Nor does a job that was already in the queue fetch it
    $this->fetch();
    $this->assertCount(0, $this->http->requests);
    $this->assertNotNull($this->source->stopped_at);
  }

  public function testFollowingAStoppedSourceAgainResumesIt() {
    $this->stop();
    $this->respondWith(200, $this->feed());
    $token = $this->microsubToken($this->user);

    // The same channel that already follows it
    $this->microsubPost($this->user, $token, ['action' => 'follow', 'channel' => $this->channel->uid, 'url' => 'https://new.example/'])
      ->assertStatus(200);

    $source = $this->source->refresh();
    $this->assertNull($source->stopped_at);
    $this->assertNull($source->failing_since);
    $this->assertEquals(0, $source->consecutive_failures);
    $this->assertNotNull($source->next_check_at);
    $this->assertCount(1, $this->http->requestsTo('GET', 'https://new.example/'));
    $this->assertEquals(2, Entry::where('source_id', $source->id)->count());
  }

  public function testFollowingAStoppedSourceFromAnotherChannelResumesIt() {
    $this->stop();
    $this->respondWith(200, $this->feed());
    $other = $this->createUser(['url' => 'https://other.example/']);

    $this->actingAs($other)->post('/channel/'.$this->homeChannel($other)->id.'/add_source', ['url' => 'https://new.example/', 'format' => 'microformats'])
      ->assertStatus(200);

    $this->assertNull($this->source->refresh()->stopped_at);
    $this->assertCount(1, $this->http->requestsTo('GET', 'https://new.example/'));
  }

  public function testRetryResumesAStoppedSource() {
    $this->stop();
    $this->respondWith(200, $this->feed());

    $this->actingAs($this->user)->post('/channel/'.$this->channel->id.'/source/'.$this->source->id.'/retry')
      ->assertStatus(200)
      ->assertExactJson(['result' => 'ok', 'stopped' => false, 'failing' => false, 'last_http_status' => 200]);

    $source = $this->source->refresh();
    $this->assertNull($source->stopped_at);
    $this->assertEquals(2, Entry::where('source_id', $source->id)->count());
  }

  public function testRetryOfAFeedThatIsStillDeadStartsAnotherThirtyDays() {
    $this->stop();
    $this->respondWith(404);

    $this->actingAs($this->user)->post('/channel/'.$this->channel->id.'/source/'.$this->source->id.'/retry')
      ->assertStatus(200)
      ->assertExactJson(['result' => 'ok', 'stopped' => false, 'failing' => true, 'last_http_status' => 404]);

    $source = $this->source->refresh();
    $this->assertNull($source->stopped_at);
    $this->assertEquals(1, $source->consecutive_failures);
    $this->assertEquals('2026-11-01 12:00:00', $source->failing_since);
    $this->assertNotNull($source->next_check_at);
  }

  public function testRetryIsOnlyForTheChannelsOwnerAndItsOwnSources() {
    $this->stop();
    $other = $this->createUser(['url' => 'https://other.example/']);

    $this->actingAs($other)->post('/channel/'.$this->channel->id.'/source/'.$this->source->id.'/retry')->assertStatus(401);

    // A source that isn't in the channel named in the URL
    $this->actingAs($other)->post('/channel/'.$this->homeChannel($other)->id.'/source/'.$this->source->id.'/retry')->assertStatus(404);

    $this->assertNotNull($this->source->refresh()->stopped_at);
    $this->assertCount(0, $this->http->requests);
  }

  public function testChannelPageExplainsStoppedAndFailingSources() {
    $page = function() { return $this->actingAs($this->user)->get('/channel/'.$this->channel->id)->assertStatus(200); };

    // Healthy
    $page()->assertDontSee('stopped checking this feed')->assertDontSee('has been failing since');

    // Failing for a while, but still being checked
    $this->source->forceFill(['consecutive_failures' => 5, 'failing_since' => '2026-10-28 09:00:00', 'last_http_status' => 503])->save();
    $page()->assertSee('This feed has been failing since October 28, 2026 (HTTP status 503)')
      ->assertSee('Aperture will stop checking it')
      ->assertDontSee('Try again');

    // Stopped
    $this->stop();
    $page()->assertSee('Aperture stopped checking this feed')
      ->assertSee('on November 1, 2026')
      ->assertSee('since October 1, 2026 failed (most recently with HTTP status 404)')
      ->assertSee('Try again')
      ->assertSee('data-source="'.$this->source->id.'"', false);
  }
}
