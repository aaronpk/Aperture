<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Entry;
use DB;

class FeedProcessorTest extends TestCase
{
  use RefreshDatabase;

  // Process a snapshot of the feed the way FetchSource does after fetching it
  private function deliver($source, $body) {
    return app(\App\Services\FeedProcessor::class)->process($source->fresh(), $body);
  }

  private function feed() {
    return file_get_contents(__DIR__.'/../data/h-feed.html');
  }

  public function testContentThatIsNotAFeedIsRejected() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://new.example/']);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->assertFalse($this->deliver($source, '{"not":"a feed"}'));
    $this->assertEquals(0, Entry::count());
  }

  public function testFeedWithNoEntriesIsValidAndFlagsOldEntriesAsGone() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://new.example/']);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);
    $this->deliver($source, $this->feed());

    $empty = '<?xml version="1.0"?><rss version="2.0"><channel><title>Empty</title><link>https://new.example/</link></channel></rss>';
    $this->assertSame(0, $this->deliver($source, $empty));

    $this->assertEquals(2, Entry::count());
    $this->assertEquals(0, Entry::where('currently_in_feed', true)->count());
  }

  public function testNewSourceBackfillsUsingPublishedDates() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://new.example/', 'is_new' => true]);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->deliver($source, $this->feed());

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

    $this->deliver($source, $this->feed());
    $this->deliver($source, $this->feed());

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
