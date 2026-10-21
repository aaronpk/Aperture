<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ChannelToken;

class MicrosubAuthTest extends TestCase
{
  use RefreshDatabase;

  public function testUnknownUserIs404() {
    $response = $this->withHeaders(['Authorization' => 'Bearer x'])->get('/microsub/999999?action=channels');
    $response->assertStatus(404);
    $response->assertJson(['error' => 'not_found']);
  }

  public function testMissingTokenIs401() {
    $user = $this->createUser();
    $response = $this->get('/microsub/'.$user->id.'?action=channels');
    $response->assertStatus(401);
    $response->assertJson(['error' => 'unauthorized', 'error_description' => 'There was no access token in the request']);
  }

  public function testNonBearerAuthorizationHeaderIs401() {
    $user = $this->createUser();
    $response = $this->withHeaders(['Authorization' => 'abc123'])->get('/microsub/'.$user->id.'?action=channels');
    $response->assertStatus(401);
    $response->assertJson(['error_description' => 'The Authorization header did not contain a Bearer token']);
  }

  public function testTokenVerifiedAtTokenEndpointAndCached() {
    $user = $this->createUser();
    $this->http->on('GET', 'https://user.example/token', 200,
      json_encode(['me' => 'https://user.example/', 'scope' => 'read follow', 'client_id' => 'https://app.example/']),
      'Content-Type: application/json');

    $response = $this->withHeaders(['Authorization' => 'Bearer remote-token'])->get('/microsub/'.$user->id.'?action=channels');
    $response->assertStatus(200);
    $this->assertCount(2, $response->json()['channels']);

    $requests = $this->http->requestsTo('GET', 'https://user.example/token');
    $this->assertCount(1, $requests);
    $this->assertContains('Authorization: Bearer remote-token', $requests[0]['headers']);

    // Second request is served from the cache
    $this->withHeaders(['Authorization' => 'Bearer remote-token'])->get('/microsub/'.$user->id.'?action=channels')->assertStatus(200);
    $this->assertCount(1, $this->http->requestsTo('GET', 'https://user.example/token'));
  }

  public function testTokenEndpointFormEncodedResponse() {
    $user = $this->createUser();
    $this->http->on('GET', 'https://user.example/token', 200,
      'me=https%3A%2F%2Fuser.example%2F&scope=read', 'Content-Type: application/x-www-form-urlencoded');

    $this->withHeaders(['Authorization' => 'Bearer t'])->get('/microsub/'.$user->id.'?action=channels')
      ->assertStatus(200);
  }

  public function testTokenEndpointRejection() {
    $user = $this->createUser();
    $this->http->on('GET', 'https://user.example/token', 401, '{"error":"invalid_token"}', 'Content-Type: application/json');

    $response = $this->withHeaders(['Authorization' => 'Bearer bad'])->get('/microsub/'.$user->id.'?action=channels');
    $response->assertStatus(403);
    $response->assertJson([
      'error' => 'forbidden',
      'token_endpoint' => ['code' => 401, 'response' => ['error' => 'invalid_token']],
    ]);
  }

  public function testTokenForDifferentUser() {
    $user = $this->createUser();
    $this->http->on('GET', 'https://user.example/token', 200,
      json_encode(['me' => 'https://someone-else.example/', 'scope' => 'read']), 'Content-Type: application/json');

    $response = $this->withHeaders(['Authorization' => 'Bearer t'])->get('/microsub/'.$user->id.'?action=channels');
    $response->assertStatus(403);
    $response->assertJson(['error' => 'invalid_user']);
  }

  public function testTokenResponseWithoutMe() {
    $user = $this->createUser();
    $this->http->on('GET', 'https://user.example/token', 200, json_encode(['scope' => 'read']), 'Content-Type: application/json');

    $response = $this->withHeaders(['Authorization' => 'Bearer t'])->get('/microsub/'.$user->id.'?action=channels');
    $response->assertStatus(400);
    $response->assertJson(['error' => 'invalid_token_response']);
  }

  public function testInsufficientScope() {
    $user = $this->createUser();
    $token = $this->microsubToken($user, ['read']);

    $response = $this->microsubPost($user, $token, ['action' => 'channels', 'name' => 'New']);
    $response->assertStatus(401);
    $response->assertJson(['error' => 'unauthorized']);
  }

  public function testUnsupportedAndUnimplementedActions() {
    $user = $this->createUser();
    $token = $this->microsubToken($user, ['read', 'follow', 'channels', 'block', 'mute']);

    $this->microsubGet($user, $token, ['action' => 'nope'])
      ->assertStatus(400)->assertJson(['error' => 'bad_request']);

    $this->microsubPost($user, $token, ['action' => 'block', 'channel' => 'x'])
      ->assertStatus(400)->assertJson(['error' => 'not_implemented']);

    $this->microsubPost($user, $token, ['action' => 'mute', 'channel' => 'x'])
      ->assertStatus(400)->assertJson(['error' => 'not_implemented']);
  }

  public function testChannelTokenIsLockedToItsChannel() {
    $user = $this->createUser();
    $home = $this->homeChannel($user);
    $other = $user->create_channel('Other');

    $source = $this->createSource($user);
    $this->addToChannel($home, $this->createEntry($source, ['name' => 'In home']));
    $this->addToChannel($other, $this->createEntry($source, ['name' => 'In other']));

    $ct = new ChannelToken();
    $ct->forceFill(['token' => 'channel-token', 'channel_id' => $home->id, 'scope' => 'read']);
    $ct->save();

    // Even when asking for another channel, the token's channel is used
    $response = $this->microsubGet($user, 'channel-token', ['action' => 'timeline', 'channel' => $other->uid]);
    $response->assertStatus(200);
    $items = $response->json()['items'];
    $this->assertCount(1, $items);
    $this->assertEquals('In home', $items[0]['name']);
  }

  public function testChannelTokenForAnotherUserIsRejected() {
    $user = $this->createUser();
    $other = $this->createUser(['url' => 'https://other.example/']);

    $ct = new ChannelToken();
    $ct->forceFill(['token' => 'channel-token', 'channel_id' => $this->homeChannel($other)->id, 'scope' => 'read']);
    $ct->save();

    $this->microsubGet($user, 'channel-token', ['action' => 'timeline'])
      ->assertStatus(403)->assertJson(['error' => 'forbidden']);
  }
}
