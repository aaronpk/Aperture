<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Entry;
use DB;

class MicropubTest extends TestCase
{
  use RefreshDatabase;

  private $user, $channel, $source;

  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->createUser();
    $this->channel = $this->homeChannel($this->user);
    $this->source = $this->createSource($this->user, ['token' => 'micropub-token', 'url' => '', 'format' => 'apikey', 'is_new' => true]);
    $this->channel->sources()->attach($this->source->id, ['created_at' => date('Y-m-d H:i:s')]);
  }

  private function micropub($body, $contentType='application/x-www-form-urlencoded', $token='micropub-token') {
    return $this->call('POST', '/micropub', [], [], [], [
      'CONTENT_TYPE' => $contentType,
      'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ], $body);
  }

  public function testConfigQuery() {
    $this->withHeaders(['Authorization' => 'Bearer micropub-token'])->get('/micropub?q=config')
      ->assertStatus(200)->assertExactJson(['media-endpoint' => 'http://aperture.test/micropub/media']);
  }

  public function testAuthErrors() {
    $this->call('POST', '/micropub', [], [], [], [], 'h=entry&content=hi')
      ->assertStatus(401)->assertJson(['error' => 'unauthorized']);
    $this->micropub('h=entry&content=hi', 'application/x-www-form-urlencoded', 'wrong')
      ->assertStatus(403)->assertJson(['error' => 'forbidden']);
    $this->call('POST', '/micropub', [], [], [], ['HTTP_AUTHORIZATION' => 'Token abc'], 'h=entry')
      ->assertStatus(401);
  }

  public function testFormEncodedPostCreatesEntryInChannel() {
    $response = $this->micropub('h=entry&content=Hello+from+micropub&category[]=test');
    $response->assertStatus(201);

    $entry = Entry::where('source_id', $this->source->id)->first();
    $this->assertNotNull($entry);
    $this->assertEquals(32, strlen($entry->unique));
    $permalink = 'http://aperture.test/entry/'.$this->source->id.'/'.$entry->unique;
    $response->assertExactJson(['url' => $permalink]);
    $this->assertEquals($permalink, $response->headers->get('Location'));

    $data = json_decode($entry->data, true);
    $this->assertEquals('entry', $data['type']);
    $this->assertEquals('Hello from micropub', $data['content']['text']);
    $this->assertEquals(['test'], $data['category']);
    $this->assertArrayHasKey('published', $data);

    $this->assertEquals(1, DB::table('channel_entry')->where('channel_id', $this->channel->id)->where('entry_id', $entry->id)->count());
    $this->assertEquals(0, $this->source->fresh()->is_new);

    // The permalink serves the entry
    $this->get('/entry/'.$this->source->id.'/'.$entry->unique)
      ->assertStatus(200)->assertJson(['type' => 'entry', '_id' => (string)$entry->id]);
  }

  public function testJsonPostWithUrlIsUpdatedInPlace() {
    $body = json_encode(['type' => ['h-entry'], 'properties' => [
      'content' => ['First version'],
      'url' => ['https://elsewhere.example/post/1'],
      'published' => ['2018-03-04T05:06:07+00:00'],
    ]]);
    $this->micropub($body, 'application/json')->assertStatus(201);

    $entry = Entry::where('source_id', $this->source->id)->first();
    $this->assertEquals(md5('https://elsewhere.example/post/1'), $entry->unique);
    $this->assertEquals('2018-03-04 05:06:07', $entry->published);

    $body = str_replace('First version', 'Second version', $body);
    $this->micropub($body, 'application/json')->assertStatus(201);

    $this->assertEquals(1, Entry::where('source_id', $this->source->id)->count());
    $this->assertStringContainsString('Second version', Entry::where('source_id', $this->source->id)->first()->data);
    $this->assertEquals(1, DB::table('channel_entry')->where('channel_id', $this->channel->id)->count());
  }

  public function testJf2Post() {
    $this->micropub(json_encode(['type' => 'entry', 'name' => 'A jf2 post', 'content' => ['text' => 'body']]), 'application/jf2+json')
      ->assertStatus(201);
    $data = json_decode(Entry::where('source_id', $this->source->id)->first()->data, true);
    $this->assertEquals('A jf2 post', $data['name']);

    $this->micropub('{"name":"no type"}', 'application/jf2+json')
      ->assertStatus(400)->assertJson(['error' => 'invalid_input']);
  }

  public function testInvalidMicropubRequest() {
    $this->micropub('content=missing+h')->assertStatus(400)->assertJsonStructure(['error', 'error_property', 'error_description']);
  }

  public function testChannelFiltersApplyToMicropub() {
    $this->channel->exclude_keywords = 'spoiler';
    $this->channel->save();

    $this->micropub('h=entry&content=This+has+a+spoiler')->assertStatus(201);
    $this->micropub('h=entry&content=This+is+fine')->assertStatus(201);

    $this->assertEquals(2, Entry::where('source_id', $this->source->id)->count());
    $texts = DB::table('channel_entry')->join('entries', 'entries.id', '=', 'channel_entry.entry_id')
      ->where('channel_id', $this->channel->id)->pluck('data')->map(function($d) { return json_decode($d, true)['content']['text']; })->all();
    $this->assertEquals(['This is fine'], $texts);
  }

  public function testTokenEndpointVerification() {
    $this->withHeaders(['Authorization' => 'Bearer micropub-token'])->get('/token')
      ->assertStatus(200)->assertExactJson([
        'me' => 'http://aperture.test/'.\p3k\b10to60($this->user->id).'-'.\p3k\b10to60($this->channel->id).'-'.\p3k\b10to60($this->source->id)
      ]);

    // A source in multiple channels can't be resolved to one channel
    $this->user->create_channel('Second')->sources()->attach($this->source->id, ['created_at' => date('Y-m-d H:i:s')]);
    \Cache::flush();
    $this->withHeaders(['Authorization' => 'Bearer micropub-token'])->get('/token')
      ->assertStatus(404)->assertJson(['error' => 'not_found']);
  }
}
