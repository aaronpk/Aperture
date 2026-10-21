<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use DB;

class MicrosubTimelineTest extends TestCase
{
  use RefreshDatabase;

  private $user, $token, $channel, $source, $entries;

  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->createUser();
    $this->token = $this->microsubToken($this->user);
    $this->channel = $this->homeChannel($this->user);
    $this->source = $this->createSource($this->user, ['name' => 'My Feed']);
    $this->entries = [];
    for($i=1; $i<=5; $i++) {
      $entry = $this->createEntry($this->source, ['name' => 'Post '.$i, 'uid' => 'uid-'.$i]);
      $this->addToChannel($this->channel, $entry, '2018-01-0'.$i.' 00:00:00');
      $this->entries[$i] = $entry;
    }
  }

  private function timeline(array $params=[]) {
    return $this->microsubGet($this->user, $this->token, array_merge(['action' => 'timeline', 'channel' => $this->channel->uid], $params));
  }

  private function seen() {
    return DB::table('channel_entry')->where('channel_id', $this->channel->id)->orderBy('entry_id')->pluck('seen', 'entry_id')->map(function($v){ return (int)$v; })->all();
  }

  public function testTimelineItemShape() {
    $response = $this->timeline();
    $response->assertStatus(200);
    $data = $response->json();

    $this->assertEquals(['Post 5', 'Post 4', 'Post 3', 'Post 2', 'Post 1'], array_column($data['items'], 'name'));

    $item = $data['items'][0];
    $this->assertSame((string)$this->entries[5]->id, $item['_id']);
    $this->assertSame((string)$this->source->id, $item['_source']);
    $this->assertSame(false, $item['_is_read']);
    $this->assertArrayNotHasKey('uid', $item);
    $this->assertEquals('entry', $item['type']);
    $this->assertEquals(['text' => 'Hello world', 'html' => '<p>Hello world</p>'], $item['content']);

    // Only a "before" cursor when everything fits on one page
    $this->assertArrayHasKey('before', $data['paging']);
    $this->assertArrayNotHasKey('after', $data['paging']);
  }

  public function testEmptyPagingIsAnObject() {
    $empty = $this->user->create_channel('Empty');
    $response = $this->microsubGet($this->user, $this->token, ['action' => 'timeline', 'channel' => $empty->uid]);
    $response->assertStatus(200);
    $this->assertStringContainsString('"paging":{}', $response->getContent());
    $this->assertStringContainsString('"items":[]', $response->getContent());
  }

  public function testReadTrackingDisabledOmitsIsRead() {
    $this->channel->read_tracking_mode = 'disabled';
    $this->channel->save();
    $item = $this->timeline()->json()['items'][0];
    $this->assertArrayNotHasKey('_is_read', $item);
  }

  public function testChannelErrors() {
    $this->microsubGet($this->user, $this->token, ['action' => 'timeline'])
      ->assertStatus(400)->assertJson(['error' => 'invalid_input']);
    $this->microsubGet($this->user, $this->token, ['action' => 'timeline', 'channel' => 'nope'])
      ->assertStatus(404)->assertJson(['error' => 'not_found']);
  }

  public function testInvalidCursor() {
    $this->timeline(['after' => 'garbage'])->assertStatus(400)->assertJson(['error' => 'invalid_cursor']);
    $this->timeline(['before' => 'garbage'])->assertStatus(400)->assertJson(['error' => 'invalid_cursor']);
  }

  public function testUnreadFilterAndSourceFilter() {
    DB::table('channel_entry')->where('entry_id', $this->entries[5]->id)->update(['seen' => 1]);
    $other = $this->createSource($this->user);
    $this->addToChannel($this->channel, $this->createEntry($other, ['name' => 'Other']), '2018-02-01 00:00:00');

    $names = array_column($this->timeline(['is_read' => 'false'])->json()['items'], 'name');
    $this->assertEquals(['Other', 'Post 4', 'Post 3', 'Post 2', 'Post 1'], $names);

    $data = $this->timeline(['source' => $this->source->id])->json();
    $this->assertCount(5, $data['items']);
    $this->assertEquals(['name' => 'My Feed'], $data['source']);
  }

  public function testMarkReadSingleAndMultiple() {
    $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => 'mark_read',
      'channel' => $this->channel->uid, 'entry' => $this->entries[1]->id])
      ->assertStatus(200)->assertJson(['result' => 'ok', 'updated' => 1]);

    $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => 'mark_read',
      'channel' => $this->channel->uid, 'entry' => [$this->entries[2]->id, $this->entries[3]->id]])
      ->assertStatus(200)->assertJson(['updated' => 2]);

    $this->assertEquals([1, 1, 1, 0, 0], array_values($this->seen()));
  }

  public function testMarkReadBeforeLastReadEntry() {
    $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => 'mark_read',
      'channel' => $this->channel->uid, 'last_read_entry' => $this->entries[3]->id])
      ->assertStatus(200)->assertJson(['updated' => 3]);
    $this->assertEquals([1, 1, 1, 0, 0], array_values($this->seen()));

    $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => 'mark_read',
      'channel' => $this->channel->uid, 'last_read_entry' => 999999])
      ->assertStatus(400)->assertJson(['error' => 'invalid_input']);
  }

  public function testMarkUnreadAndRemove() {
    DB::table('channel_entry')->where('channel_id', $this->channel->id)->update(['seen' => 1]);

    $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => 'mark_unread',
      'channel' => $this->channel->uid, 'entry' => $this->entries[2]->id])
      ->assertStatus(200)->assertJson(['updated' => 1]);
    $this->assertEquals([1, 0, 1, 1, 1], array_values($this->seen()));

    $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => 'remove',
      'channel' => $this->channel->uid, 'entry' => [$this->entries[4]->id, $this->entries[5]->id]])
      ->assertStatus(200)->assertJson(['updated' => 2]);
    $this->assertCount(3, $this->timeline()->json()['items']);
  }

  public function testTimelinePostErrors() {
    foreach(['mark_read', 'mark_unread', 'remove'] as $method) {
      $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => $method, 'channel' => $this->channel->uid])
        ->assertStatus(400)->assertJson(['error' => 'invalid_input']);
    }
    $this->microsubPost($this->user, $this->token, ['action' => 'timeline', 'method' => 'bogus', 'channel' => $this->channel->uid])
      ->assertStatus(400)->assertJson(['error' => 'invalid_method']);
  }
}
