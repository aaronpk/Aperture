<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Entry;
use DB;

class WebSubReceiverTest extends TestCase
{
  use RefreshDatabase;

  private function deliver($source, $body) {
    return $this->call('POST', '/websub/source/'.$source->token, [], [], [], ['CONTENT_TYPE' => 'text/html'], $body);
  }

  private function feed() {
    return file_get_contents(__DIR__.'/../data/h-feed.html');
  }

  public function testUnknownSourceIs404() {
    $this->call('POST', '/websub/source/nope', [], [], [], [], $this->feed())
      ->assertStatus(404)->assertJson(['error' => 'not_found']);
  }

  public function testSourceWithoutChannelsIsUnsubscribed() {
    $user = $this->createUser();
    $source = $this->createSource($user, ['url' => 'https://new.example/']);

    $this->deliver($source, $this->feed())->assertStatus(200)->assertExactJson(['result' => 'empty']);

    $unsubs = $this->http->requestsTo('POST', 'https://watchtower.test/');
    $this->assertCount(1, $unsubs);
    $this->assertStringContainsString('hub.mode=unsubscribe', $unsubs[0]['body']);
    $this->assertEquals(0, Entry::count());
  }

  public function testNewSourceBackfillsUsingPublishedDates() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://new.example/', 'is_new' => true]);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->deliver($source, $this->feed())->assertStatus(200);

    $entries = Entry::where('source_id', $source->id)->orderBy('id')->get();
    $this->assertCount(2, $entries);
    $this->assertEquals('https://new.example/2', $entries[0]->unique);
    $this->assertEquals('2018-01-02 10:00:00', $entries[0]->published);
    $this->assertEquals(1, $entries[0]->currently_in_feed);

    $data = json_decode($entries[0]->data, true);
    $this->assertEquals('entry', $data['type']);
    $this->assertEquals('Second post', $data['content']['text']);

    $rows = DB::table('channel_entry')->where('channel_id', $channel->id)->orderBy('entry_id')->get();
    $this->assertCount(2, $rows);
    $this->assertEquals('2018-01-02 10:00:00', $rows[0]->created_at);
    $this->assertEquals(1, $rows[0]->seen);
    $this->assertEquals(0, $rows[0]->batch_order);
    $this->assertEquals(1, $rows[1]->batch_order);

    $source->refresh();
    $this->assertEquals(0, $source->is_new);
  }

  public function testExistingSourceAddsUnreadEntriesOnce() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://new.example/']);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->deliver($source, $this->feed())->assertStatus(200);
    $this->deliver($source, $this->feed())->assertStatus(200);

    $this->assertEquals(2, Entry::where('source_id', $source->id)->count());
    $rows = DB::table('channel_entry')->where('channel_id', $channel->id)->get();
    $this->assertCount(2, $rows);
    foreach($rows as $row) {
      $this->assertEquals(0, $row->seen);
      $this->assertNotEquals('2018-01-02 10:00:00', $row->created_at);
    }
  }

  public function testEntriesDroppedFromFeedAreFlagged() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://new.example/']);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->deliver($source, $this->feed());

    // Second delivery only contains the newer post
    $partial = preg_replace('/<div class="h-entry">\s*<a class="u-url" href="https:\/\/new.example\/1">.*?<\/div>/s', '', $this->feed());
    $this->deliver($source, $partial);

    $this->assertEquals(1, Entry::where('unique', 'https://new.example/2')->first()->currently_in_feed);
    $this->assertEquals(0, Entry::where('unique', 'https://new.example/1')->first()->currently_in_feed);
  }

  public function testChannelFiltersAreApplied() {
    $user = $this->createUser();
    $photos = $this->homeChannel($user);
    $photos->include_only = 'photos_videos';
    $photos->save();

    $noPhotoTag = $user->create_channel('No photo keyword');
    $noPhotoTag->exclude_keywords = 'photo';
    $noPhotoTag->save();

    $source = $this->createSource($user, ['url' => 'https://new.example/']);
    $photos->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);
    $noPhotoTag->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->deliver($source, $this->feed());

    $inChannel = function($channel) {
      return DB::table('channel_entry')->join('entries', 'entries.id', '=', 'channel_entry.entry_id')
        ->where('channel_id', $channel->id)->pluck('unique')->all();
    };
    $this->assertEquals(['https://new.example/1'], $inChannel($photos));
    $this->assertEquals(['https://new.example/2'], $inChannel($noPhotoTag));
  }
}
