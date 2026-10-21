<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MicrosubChannelsTest extends TestCase
{
  use RefreshDatabase;

  public function testNewUserHasNotificationsAndHome() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);

    $response = $this->microsubGet($user, $token, ['action' => 'channels']);
    $response->assertStatus(200);

    $channels = $response->json()['channels'];
    $this->assertCount(2, $channels);
    $this->assertEquals(['uid' => 'notifications', 'name' => 'Notifications', 'unread' => 0], $channels[0]);
    $this->assertEquals('Home', $channels[1]['name']);
    $this->assertEquals(24, strlen($channels[1]['uid']));
  }

  public function testUnreadCountsByReadTrackingMode() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $home = $this->homeChannel($user);
    $source = $this->createSource($user);

    $this->addToChannel($home, $this->createEntry($source), '2018-01-01 00:00:00', 0, 0);
    $this->addToChannel($home, $this->createEntry($source), '2018-01-01 00:00:00', 1, 0);
    $this->addToChannel($home, $this->createEntry($source), '2018-01-01 00:00:00', 2, 1);

    $channels = $this->microsubGet($user, $token, ['action' => 'channels'])->json()['channels'];
    $this->assertSame(2, $channels[1]['unread']);

    $home->read_tracking_mode = 'boolean';
    $home->save();
    $channels = $this->microsubGet($user, $token, ['action' => 'channels'])->json()['channels'];
    $this->assertSame(true, $channels[1]['unread']);

    $home->read_tracking_mode = 'disabled';
    $home->default_destination = 'https://user.example/micropub';
    $home->save();
    $channels = $this->microsubGet($user, $token, ['action' => 'channels'])->json()['channels'];
    $this->assertArrayNotHasKey('unread', $channels[1]);
    $this->assertEquals('https://user.example/micropub', $channels[1]['destination']);
  }

  public function testCreateChannel() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);

    $response = $this->microsubPost($user, $token, ['action' => 'channels', 'name' => 'Friends']);
    $response->assertStatus(200);
    $this->assertEquals('Friends', $response->json()['name']);

    // New channels are inserted directly after notifications
    $channels = $this->microsubGet($user, $token, ['action' => 'channels'])->json()['channels'];
    $this->assertEquals(['Notifications', 'Friends', 'Home'], array_column($channels, 'name'));
  }

  public function testCreateChannelValidation() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);

    $this->microsubPost($user, $token, ['action' => 'channels', 'name' => '  '])
      ->assertStatus(400)->assertJson(['error' => 'invalid_input']);

    $this->microsubPost($user, $token, ['action' => 'channels'])
      ->assertStatus(400)->assertJson(['error' => 'invalid_input']);

    $this->microsubPost($user, $token, ['action' => 'channels', 'name' => 'Home'])
      ->assertStatus(400)->assertJson(['error' => 'duplicate']);
  }

  public function testRenameChannel() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $home = $this->homeChannel($user);

    $this->microsubPost($user, $token, ['action' => 'channels', 'channel' => $home->uid, 'name' => 'Renamed'])
      ->assertStatus(200)->assertJson(['uid' => $home->uid, 'name' => 'Renamed']);

    $this->microsubPost($user, $token, ['action' => 'channels', 'channel' => 'notifications', 'name' => 'x'])
      ->assertStatus(400)->assertJson(['error_description' => 'Cannot rename system channels']);

    $this->microsubPost($user, $token, ['action' => 'channels', 'channel' => 'nope', 'name' => 'x'])
      ->assertStatus(400)->assertJson(['error_description' => 'Channel not found']);
  }

  public function testOrderChannels() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $home = $this->homeChannel($user);
    $a = $user->create_channel('A');
    $b = $user->create_channel('B');

    $response = $this->microsubPost($user, $token, ['action' => 'channels', 'method' => 'order',
      'channels' => [$home->uid, $a->uid, $b->uid]]);
    $response->assertStatus(200);
    $this->assertEquals([$home->uid, $a->uid, $b->uid], $response->json()['channels']);

    $channels = $this->microsubGet($user, $token, ['action' => 'channels'])->json()['channels'];
    $this->assertEquals(['Notifications', 'Home', 'A', 'B'], array_column($channels, 'name'));

    $this->microsubPost($user, $token, ['action' => 'channels', 'method' => 'order', 'channels' => ['nope']])
      ->assertStatus(400)->assertJson(['error_description' => 'One or more channels were not found']);

    $this->microsubPost($user, $token, ['action' => 'channels', 'method' => 'order', 'channels' => 'x'])
      ->assertStatus(400)->assertJson(['error_description' => 'channels parameter must be an array']);
  }

  public function testDeleteChannel() {
    $user = $this->createUser();
    $token = $this->microsubToken($user);
    $channel = $user->create_channel('Temp');
    $source = $this->createSource($user);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->microsubPost($user, $token, ['action' => 'channels', 'method' => 'delete', 'channel' => $channel->uid])
      ->assertStatus(200)->assertJson(['deleted' => true]);

    $channels = $this->microsubGet($user, $token, ['action' => 'channels'])->json()['channels'];
    $this->assertNotContains('Temp', array_column($channels, 'name'));

    $this->microsubPost($user, $token, ['action' => 'channels', 'method' => 'delete', 'channel' => 'notifications'])
      ->assertStatus(400)->assertJson(['error_description' => 'Cannot delete system channels']);
  }
}
