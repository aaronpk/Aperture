<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Source;

class MicrosubFollowTest extends TestCase
{
  use RefreshDatabase;

  public function testFollowNewSourceSubscribesViaWatchtower() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $channel = $this->homeChannel($user);

    $response = $this->microsubPost($user, $token, ['action' => 'follow', 'channel' => $channel->uid, 'url' => 'https://blog.example/']);
    $response->assertStatus(200);
    $response->assertExactJson(['type' => 'feed', 'url' => 'https://blog.example/']);

    $source = Source::where('url', 'https://blog.example/')->first();
    $this->assertNotNull($source);
    $this->assertEquals(32, strlen($source->token));
    $this->assertEquals($user->id, $source->created_by);
    $this->assertEquals(1, $source->is_new);

    $subs = $this->http->requestsTo('POST', 'https://watchtower.test/');
    $this->assertCount(1, $subs);
    $this->assertEquals(http_build_query([
      'hub.mode' => 'subscribe',
      'hub.topic' => 'https://blog.example/',
      'hub.callback' => 'http://aperture.test/websub/source/'.$source->token,
    ]), $subs[0]['body']);
    $this->assertContains('Authorization: Bearer watchtower-token', $subs[0]['headers']);

    $following = $this->microsubGet($user, $token, ['action' => 'follow', 'channel' => $channel->uid])->json();
    $this->assertEquals(['items' => [['type' => 'feed', 'url' => 'https://blog.example/']]], $following);
  }

  public function testFollowExistingSourceBackfillsEntries() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $first = $this->homeChannel($user);
    $second = $user->create_channel('Second');

    $source = $this->createSource($user, ['url' => 'https://blog.example/']);
    $first->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);
    $this->createEntry($source, ['name' => 'Old post', 'published' => '2017-06-01T00:00:00+00:00']);

    $this->microsubPost($user, $token, ['action' => 'follow', 'channel' => $second->uid, 'url' => 'https://blog.example/'])
      ->assertStatus(200);

    // Already subscribed via the first channel, so no new subscription
    $this->assertCount(0, $this->http->requestsTo('POST', 'https://watchtower.test/'));

    $items = $this->microsubGet($user, $token, ['action' => 'timeline', 'channel' => $second->uid])->json()['items'];
    $this->assertEquals(['Old post'], array_column($items, 'name'));
    $this->assertSame(true, $items[0]['_is_read']);
  }

  public function testFollowListIncludesCustomName() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://blog.example/']);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s'), 'name' => 'Custom']);

    $following = $this->microsubGet($user, $token, ['action' => 'follow', 'channel' => $channel->uid])->json();
    $this->assertEquals([['type' => 'feed', 'url' => 'https://blog.example/', 'name' => 'Custom']], $following['items']);
  }

  public function testUnfollow() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://blog.example/']);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->microsubPost($user, $token, ['action' => 'unfollow', 'channel' => $channel->uid, 'url' => 'https://blog.example/'])
      ->assertStatus(200)->assertExactJson(['type' => 'feed', 'url' => 'https://blog.example/']);

    $this->assertCount(0, $channel->sources()->get());

    // Last channel removed, so the source is unsubscribed
    $unsubs = $this->http->requestsTo('POST', 'https://watchtower.test/');
    $this->assertCount(1, $unsubs);
    $this->assertContains('hub.mode=unsubscribe', $unsubs[0]['body']);

    // Unknown URL returns an empty body
    $response = $this->microsubPost($user, $token, ['action' => 'unfollow', 'channel' => $channel->uid, 'url' => 'https://nope.example/']);
    $response->assertStatus(200);
    $this->assertSame('', $response->getContent());
  }

  public function testPreviewKnownSourceUsesStoredEntries() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $source = $this->createSource($user, ['url' => 'https://blog.example/']);
    $this->createEntry($source, ['name' => 'Stored post']);

    $items = $this->microsubGet($user, $token, ['action' => 'preview', 'url' => 'https://blog.example/'])->json()['items'];
    $this->assertEquals(['Stored post'], array_column($items, 'name'));
    $this->assertCount(0, $this->http->requests);
  }

  public function testPreviewUnknownSourceFetchesFeed() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $this->http->on('GET', 'https://new.example/', 200, file_get_contents(__DIR__.'/../data/h-feed.html'));

    $items = $this->microsubGet($user, $token, ['action' => 'preview', 'url' => 'https://new.example/'])->json()['items'];
    $this->assertCount(2, $items);
    $this->assertEquals('https://new.example/2', $items[0]['url']);
    $this->assertEquals('Second post', $items[0]['content']['text']);
  }

  public function testSearchFindsFeeds() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $this->http->on('GET', 'https://new.example/', 200, file_get_contents(__DIR__.'/../data/h-feed.html'));

    // A bare word gets ".com" appended, a domain gets a scheme
    $this->microsubPost($user, $token, ['action' => 'search', 'query' => 'example']);
    $this->assertCount(1, $this->http->requestsTo('GET', 'http://example.com/'));

    $results = $this->microsubPost($user, $token, ['action' => 'search', 'query' => 'https://new.example/'])->json()['results'];
    $this->assertNotEmpty($results);
    foreach($results as $r) {
      $this->assertEquals('feed', $r['type']);
    }
    $this->assertContains('https://new.example/feed.xml', array_column($results, 'url'));

    $this->microsubPost($user, $token, ['action' => 'search'])
      ->assertStatus(400)->assertJson(['error' => 'invalid_query']);
    $this->microsubPost($user, $token, ['action' => 'search', 'query' => 'x', 'channel' => 'y'])
      ->assertStatus(400)->assertJson(['error' => 'not_implemented']);
  }
}
