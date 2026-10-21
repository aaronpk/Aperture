<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Source;

class IndieAuthTest extends TestCase
{
  use RefreshDatabase;

  private function authParams(array $override=[]) {
    return array_merge([
      'client_id' => 'https://app.example/',
      'redirect_uri' => 'https://app.example/callback',
      'response_type' => 'code',
      'state' => 'xyz',
      'scope' => 'create',
    ], $override);
  }

  public function testAuthRequestValidation() {
    $cases = [
      ['client_id' => ''],
      ['client_id' => 'not a url'],
      ['redirect_uri' => ''],
      ['redirect_uri' => 'nope'],
      ['response_type' => 'id'],
    ];
    foreach($cases as $override) {
      $this->get('/auth?'.http_build_query($this->authParams($override)))->assertStatus(400);
    }
  }

  public function testAuthRequiresLogin() {
    $query = http_build_query($this->authParams());
    $response = $this->get('/auth?'.$query);
    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    $this->assertStringStartsWith('http://aperture.test/login?return=', $location);
    parse_str(parse_url($location, PHP_URL_QUERY), $loginParams);
    $this->assertStringStartsWith('http://aperture.test/auth?', $loginParams['return']);
    parse_str(parse_url($loginParams['return'], PHP_URL_QUERY), $returnParams);
    $this->assertEquals($this->authParams(), $returnParams);
  }

  public function testAuthRejectsNonCreateScope() {
    $user = $this->createUser();
    $this->actingAs($user)->get('/auth?'.http_build_query($this->authParams(['scope' => 'read'])))
      ->assertStatus(400)->assertSee('invalid scope');
  }

  public function testAuthPromptShowsAppAndChannels() {
    $user = $this->createUser();
    $this->http->on('GET', 'https://app.example/', 200,
      '<div class="h-app"><a href="/" class="u-url p-name">Example App</a><img class="u-logo" src="/logo.png"></div>');

    $response = $this->actingAs($user)->get('/auth?'.http_build_query($this->authParams()));
    $response->assertStatus(200);
    $response->assertSee('Example App');
    $response->assertSee('Home');
    $response->assertSee('https://app.example/callback');
  }

  public function testFullAuthorizationCodeFlow() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);

    $response = $this->actingAs($user)->post('/auth/process', $this->authParams(['channel' => $channel->id]));
    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    $this->assertStringStartsWith('https://app.example/callback?', $location);
    parse_str(parse_url($location, PHP_URL_QUERY), $query);
    $this->assertEquals('xyz', $query['state']);
    $this->assertStringStartsWith('a.', $query['code']);

    // Codes must stay valid for longer than a second (Laravel 5.8+ cache TTLs are in seconds, not minutes)
    sleep(2);

    $response = $this->post('/token', [
      'grant_type' => 'authorization_code',
      'code' => $query['code'],
      'client_id' => 'https://app.example/',
      'redirect_uri' => 'https://app.example/callback',
    ]);
    $response->assertStatus(200);
    $data = $response->json();

    $source = Source::where('token', $data['access_token'])->first();
    $this->assertNotNull($source);
    $this->assertEquals('https://app.example/', $source->name);
    $this->assertEquals('apikey', $source->format);
    $this->assertEquals(1, $source->download_images);
    $this->assertEquals('create', $data['scope']);
    $this->assertEquals('http://aperture.test/'.\p3k\b10to60($user->id).'-'.\p3k\b10to60($channel->id).'-'.\p3k\b10to60($source->id), $data['me']);
    $this->assertEquals(1, $channel->sources()->where('source_id', $source->id)->count());

    // The profile URL renders with IndieAuth discovery links
    $this->get(parse_url($data['me'], PHP_URL_PATH))
      ->assertStatus(200)->assertSee('<link rel="token_endpoint" href="http://aperture.test/token">', false);
  }

  public function testSecondAuthorizationReusesTheSameSource() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $tokens = [];
    foreach([1, 2] as $i) {
      $location = $this->actingAs($user)->post('/auth/process', $this->authParams(['channel' => $channel->id, 'scope' => 'save']))
        ->headers->get('Location');
      parse_str(parse_url($location, PHP_URL_QUERY), $query);
      $data = $this->post('/token', ['code' => $query['code'], 'client_id' => 'https://app.example/', 'redirect_uri' => 'https://app.example/callback'])->json();
      $this->assertEquals('save', $data['scope']);
      $tokens[] = $data['access_token'];
    }
    $this->assertEquals($tokens[0], $tokens[1]);
  }

  public function testTokenRequestValidation() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $location = $this->actingAs($user)->post('/auth/process', $this->authParams(['channel' => $channel->id]))->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);

    $this->post('/token', ['code' => 'a.bogus', 'client_id' => 'https://app.example/', 'redirect_uri' => 'https://app.example/callback'])
      ->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    $this->post('/token', ['code' => $query['code'], 'client_id' => 'https://evil.example/', 'redirect_uri' => 'https://app.example/callback'])
      ->assertStatus(400)->assertJson(['error' => 'invalid_request']);
    $this->post('/token', ['code' => $query['code'], 'client_id' => 'https://app.example/', 'redirect_uri' => 'https://evil.example/'])
      ->assertStatus(400)->assertJson(['error' => 'invalid_request']);
  }

  public function testAuthProcessRejectsOtherUsersChannel() {
    $user = $this->createUser();
    $other = $this->createUser(['url' => 'https://other.example/']);
    $this->actingAs($user)->post('/auth/process', $this->authParams(['channel' => $this->homeChannel($other)->id]))
      ->assertRedirect('login');
  }

  public function testAuthPostIsUnsupported() {
    $this->post('/auth', ['code' => 'x'])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
  }
}
