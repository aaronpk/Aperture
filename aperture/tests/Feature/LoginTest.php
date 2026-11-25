<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class LoginTest extends TestCase
{
  use RefreshDatabase;

  const METADATA_URL = 'https://auth.example/.well-known/oauth-authorization-server';

  protected function setUp(): void {
    parent::setUp();
    config(['aperture.public_access' => true]);
  }

  // The IndieAuth client remembers pages it has fetched for the life of the process,
  // so every test uses its own identity URL
  private function site($name) {
    return 'https://'.$name.'.example/';
  }

  private function siteWithRelLinks($name) {
    $url = $this->site($name);
    $this->http->on('GET', $url, 200, '<html><head>
      <link rel="authorization_endpoint" href="https://rel-auth.example/auth">
      <link rel="token_endpoint" href="https://rel-auth.example/token">
      </head><body></body></html>');
    return $url;
  }

  // An identity that only advertises its IndieAuth server's metadata, with no
  // authorization_endpoint or token_endpoint links of its own
  private function siteWithMetadataOnly($name, array $metadata=[]) {
    $url = $this->site($name);
    $this->http->on('GET', $url, 200, '<html><head>
      <link rel="indieauth-metadata" href="'.self::METADATA_URL.'">
      </head><body></body></html>');
    $this->http->on('GET', self::METADATA_URL, 200, json_encode(array_merge([
      'issuer' => 'https://auth.example/',
      'authorization_endpoint' => 'https://auth.example/authorize',
      'token_endpoint' => 'https://auth.example/token',
      'introspection_endpoint' => 'https://auth.example/introspect',
      'code_challenge_methods_supported' => ['S256'],
      'authorization_response_iss_parameter_supported' => true,
    ], $metadata)), 'Content-Type: application/json');
    return $url;
  }

  private function startLogin($url) {
    $response = $this->post('/login', ['url' => $url]);
    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    parse_str((string)parse_url($location, PHP_URL_QUERY), $params);
    return [$location, $params];
  }

  private function tokenEndpointReturns($endpoint, $me) {
    $this->http->on('POST', $endpoint, 200, json_encode(['me' => $me]), 'Content-Type: application/json');
  }

  // Login used to redirect to whatever URL was passed as "return", to any site
  public function testLoginIgnoresAReturnUrl() {
    $url = $this->siteWithRelLinks('return-login');
    $this->assertStringNotContainsString('evil.example', $this->get('/login?return=https://evil.example/')->getContent());

    $location = $this->post('/login', ['url' => $url, 'return' => 'https://evil.example/'])->headers->get('Location');
    parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

    $this->tokenEndpointReturns('https://rel-auth.example/token', $url);
    $this->get('/login/callback?code=abc&state='.$params['state'])->assertRedirect('http://aperture.test/dashboard');
  }

  public function testLoginWithRelLinks() {
    $url = $this->siteWithRelLinks('rel-login');

    list($location, $params) = $this->startLogin($url);
    $this->assertStringStartsWith('https://rel-auth.example/auth?', $location);
    $this->assertEquals($url, $params['me']);
    $this->assertEquals('http://aperture.test/', $params['client_id']);
    $this->assertEquals('http://aperture.test/login/callback', $params['redirect_uri']);
    $this->assertEquals('S256', $params['code_challenge_method']);

    // No metadata, so nothing about an issuer is expected in the callback
    $this->tokenEndpointReturns('https://rel-auth.example/token', $url);
    $this->get('/login/callback?code=abc&state='.$params['state'])->assertRedirect('http://aperture.test/dashboard');

    $user = User::where('url', $url)->first();
    $this->assertNotNull($user);
    $this->assertEquals('https://rel-auth.example/token', $user->token_endpoint);
    $this->assertAuthenticatedAs($user);

    $exchange = $this->http->requestsTo('POST', 'https://rel-auth.example/token');
    $this->assertCount(1, $exchange);
    parse_str($exchange[0]['body'], $sent);
    $this->assertEquals('abc', $sent['code']);
    $this->assertEquals('authorization_code', $sent['grant_type']);
    $this->assertNotEmpty($sent['code_verifier']);
  }

  public function testLoginWithMetadataOnlyIdentity() {
    $url = $this->siteWithMetadataOnly('metadata-login');

    list($location, $params) = $this->startLogin($url);
    $this->assertStringStartsWith('https://auth.example/authorize?', $location);
    $this->assertEquals($url, $params['me']);

    $this->tokenEndpointReturns('https://auth.example/token', $url);
    $this->get('/login/callback?code=abc&state='.$params['state'].'&iss='.urlencode('https://auth.example/'))
      ->assertRedirect('http://aperture.test/dashboard');

    $user = User::where('url', $url)->first();
    $this->assertNotNull($user);
    $this->assertEquals('https://auth.example/token', $user->token_endpoint);
    $this->assertAuthenticatedAs($user);
  }

  public function testCallbackMustNameTheIssuerFromTheMetadata() {
    $url = $this->siteWithMetadataOnly('metadata-issuer');
    $this->tokenEndpointReturns('https://auth.example/token', $url);

    foreach(['', '&iss='.urlencode('https://evil.example/')] as $iss) {
      list($location, $params) = $this->startLogin($url);
      $this->get('/login/callback?code=abc&state='.$params['state'].$iss)
        ->assertStatus(200)->assertSee('invalid issuer');
    }

    // The code is never sent anywhere when the issuer is wrong
    $this->assertCount(0, $this->http->requestsTo('POST', 'https://auth.example/token'));
    $this->assertGuest();
    $this->assertNull(User::where('url', $url)->first());
  }

  public function testLoginIsRefusedWhenTheMetadataIssuerIsNotValid() {
    // The issuer has to be a prefix of the metadata URL
    $url = $this->siteWithMetadataOnly('metadata-bad-issuer', ['issuer' => 'https://someone-else.example/']);

    $response = $this->post('/login', ['url' => $url]);
    $response->assertRedirect('login');
    $response->assertSessionHas('auth_error', 'invalid issuer');
  }

  public function testSiteWithoutMetadataDoesNotInheritAnotherSitesMetadata() {
    $this->startLogin($this->siteWithMetadataOnly('metadata-first'));

    list($location) = $this->startLogin($this->siteWithRelLinks('rel-second'));
    $this->assertStringStartsWith('https://rel-auth.example/auth?', $location);

    // And a site with neither still fails cleanly
    $none = $this->site('no-endpoints');
    $this->http->on('GET', $none, 200, '<html><body></body></html>');
    $this->post('/login', ['url' => $none])->assertSessionHas('auth_error', 'missing authorization endpoint');
  }

  public function testCreateUserWithMetadataOnlyIdentity() {
    $url = $this->siteWithMetadataOnly('metadata-cli');

    $this->artisan('create:user', ['url' => $url])
      ->expectsOutput('Found token endpoint: https://auth.example/token')
      ->assertExitCode(0);

    $this->assertEquals('https://auth.example/token', User::where('url', $url)->first()->token_endpoint);
  }

  public function testCreateUserStillFailsWithoutATokenEndpoint() {
    $url = $this->site('cli-no-endpoints');
    $this->http->on('GET', $url, 200, '<html><body></body></html>');

    $this->artisan('create:user', ['url' => $url])
      ->expectsOutput('Could not discover the token endpoint for this user so they will be unable to log in');
    $this->assertNull(User::where('url', $url)->first());
  }

  public function testApiRegistrationWithMetadataOnlyIdentity() {
    $url = $this->siteWithMetadataOnly('metadata-api');

    // The verification endpoint has to echo the challenge it is sent. Bind a transport
    // that does that for this one URL.
    $inner = $this->http;
    $this->http = new class($inner) extends \Tests\Support\FakeTransport {
      private $inner;
      public function __construct($inner) { $this->inner = $inner; }
      public function post($url, $body, $headers=[]) {
        if(strpos($url, 'https://verify.example/') === 0) {
          $this->requests[] = ['method' => 'POST', 'url' => $url, 'body' => $body, 'headers' => $headers];
          parse_str($body, $params);
          return ['code' => 200, 'header' => "HTTP/1.1 200\r\nContent-Type: text/plain", 'body' => $params['challenge'] ?? '', 'error' => '', 'error_description' => '', 'url' => $url, 'debug' => ''];
        }
        return $this->inner->post($url, $body, $headers);
      }
      public function get($url, $headers=[]) { return $this->inner->get($url, $headers); }
      public function head($url, $headers=[]) { return $this->inner->head($url, $headers); }
    };
    \IndieAuth\Client::$http = app(\p3k\HTTP::class);

    $this->post('/api/register', ['site' => $url, 'code' => 'reg-code', 'verification_endpoint' => 'https://verify.example/', 'via' => 'test']);

    $user = User::where('url', $url)->first();
    $this->assertNotNull($user);
    $this->assertEquals('https://auth.example/token', $user->token_endpoint);

    // The last call tells the site its Microsub endpoint
    $calls = $this->http->requestsTo('POST', 'https://verify.example/');
    parse_str(end($calls)['body'], $last);
    $this->assertEquals('http://aperture.test/microsub/'.$user->id, $last['microsub']);
  }
}
