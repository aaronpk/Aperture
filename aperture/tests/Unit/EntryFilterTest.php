<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Entry, App\Models\Channel;

class EntryFilterTest extends TestCase
{
  private function entry(array $data) {
    $entry = new Entry();
    $entry->data = json_encode(array_merge(['type' => 'entry'], $data));
    return $entry;
  }

  public function testPostTypeDiscovery() {
    $cases = [
      'event' => ['type' => 'event'],
      'card' => ['type' => 'card'],
      'review' => ['type' => 'review'],
      'recipe' => ['type' => 'recipe'],
      'rsvp' => ['rsvp' => 'yes', 'in-reply-to' => ['x']],
      'repost' => ['repost-of' => ['x']],
      'like' => ['like-of' => ['x']],
      'reply' => ['in-reply-to' => ['x']],
      'bookmark' => ['bookmark-of' => ['x']],
      'checkin' => ['checkin' => ['name' => 'x']],
      'video' => ['video' => ['x'], 'photo' => ['y']],
      'photo' => ['photo' => ['x']],
      'article' => ['name' => 'Title', 'content' => ['text' => 'x']],
      'note' => ['content' => ['text' => 'x']],
    ];
    foreach($cases as $expected => $data) {
      $this->assertEquals($expected, $this->entry($data)->post_type(), $expected);
    }
  }

  public function testMatchesKeyword() {
    $this->assertTrue($this->entry(['name' => 'Hello World'])->matches_keyword('world'));
    $this->assertTrue($this->entry(['content' => ['text' => 'about PHP']])->matches_keyword('php'));
    $this->assertTrue($this->entry(['category' => ['IndieWeb']])->matches_keyword('indieweb'));
    $this->assertFalse($this->entry(['category' => ['IndieWebCamp']])->matches_keyword('indieweb'));
    $this->assertFalse($this->entry(['content' => ['text' => 'nothing']])->matches_keyword('php'));
  }

  public function testShouldAddEntry() {
    $channel = new Channel();
    $photo = $this->entry(['photo' => ['x'], 'content' => ['text' => 'cat pic']]);
    $checkinWithPhoto = $this->entry(['checkin' => ['name' => 'x'], 'photo' => ['x'], 'content' => ['text' => 'here']]);
    $article = $this->entry(['name' => 'An article', 'content' => ['text' => 'long text']]);
    $repost = $this->entry(['repost-of' => ['x'], 'content' => ['text' => 'rt']]);

    $this->assertTrue($channel->should_add_entry($article));

    $channel->include_only = 'photos_videos';
    $this->assertTrue($channel->should_add_entry($photo));
    $this->assertTrue($channel->should_add_entry($checkinWithPhoto));
    $this->assertFalse($channel->should_add_entry($article));

    $channel->include_only = 'articles';
    $this->assertTrue($channel->should_add_entry($article));
    $channel->include_keywords = 'cats dogs';
    $this->assertFalse($channel->should_add_entry($article));
    $channel->include_keywords = 'nope article';
    $this->assertTrue($channel->should_add_entry($article));

    $channel->include_only = '';
    $channel->include_keywords = null;
    $channel->exclude_types = 'repost like';
    $this->assertFalse($channel->should_add_entry($repost));
    $this->assertTrue($channel->should_add_entry($photo));

    $channel->exclude_keywords = 'cat';
    $this->assertFalse($channel->should_add_entry($photo));
  }
}
