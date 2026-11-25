<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\FakeDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\Entry, App\Models\Media;
use App\Jobs\FetchSource;
use App\Events\EntrySaved;
use App\Services\SafeDownload;
use App\Providers\AppServiceProvider;

/**
 * Nothing that fetches a URL on someone's behalf may reach an address that isn't on
 * the public internet. Each test points one of those places at such an address and
 * checks that no request was made.
 */
class SsrfTest extends TestCase
{
  use RefreshDatabase;

  // 1x1 transparent PNG
  const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

  private $user;
  private $channel;

  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->createUser();
    $this->channel = $this->homeChannel($this->user);

    // A public-looking name that points at a private address
    $this->dns['internal.example'] = ['10.1.2.3'];
    // A public host that redirects to a private address
    $this->http->on('GET', 'https://redirect.example/', 302, '', "Location: http://127.0.0.1/secret");
    $this->http->on('POST', 'https://redirect.example/', 307, '', "Location: http://127.0.0.1/secret");
    // If anything private were reached, this is what it would return
    foreach(self::privateUrls() as $case)
      $this->http->on('GET', $case[0], 200, '<html><body class="h-feed"><div class="h-entry"><p class="p-name">SECRET</p></div></body></html>');
  }

  public static function privateUrls() {
    return [
      'loopback' => ['http://127.0.0.1/'],
      'loopback with port' => ['http://127.0.0.1:6379/'],
      'localhost' => ['http://localhost/'],
      'private 10/8' => ['http://10.0.0.5/'],
      'private 192.168' => ['http://192.168.1.1/admin'],
      'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
      'IPv6 loopback' => ['http://[::1]/'],
      'IPv4-mapped IPv6' => ['http://[::ffff:127.0.0.1]/'],
      'decimal IP' => ['http://2130706433/'],
      'name resolving to a private address' => ['http://internal.example/'],
    ];
  }

  private function assertNothingPrivateWasRequested() {
    foreach($this->http->requests as $request) {
      $host = parse_url($request['url'], PHP_URL_HOST);
      $this->assertContains($host, ['redirect.example', 'user.example', 'app.example'], 'A request was made to '.$request['url']);
    }
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('privateUrls')]
  public function testPreviewSearchAndFindFeedsRefusePrivateAddresses($url) {
    $this->dns['localhost'] = ['127.0.0.1'];
    $token = $this->microsubToken($this->user);

    $preview = $this->microsubGet($this->user, $token, ['action' => 'preview', 'url' => $url]);
    $this->assertStringNotContainsString('SECRET', $preview->getContent());

    $search = $this->microsubPost($this->user, $token, ['action' => 'search', 'query' => $url]);
    $this->assertStringNotContainsString('SECRET', $search->getContent());

    $this->actingAs($this->user)->post('/source/find_feeds', ['url' => $url]);

    $this->assertCount(0, $this->http->requests);
  }

  public function testRedirectToAPrivateAddressIsNotFollowed() {
    $token = $this->microsubToken($this->user);
    $preview = $this->microsubGet($this->user, $token, ['action' => 'preview', 'url' => 'https://redirect.example/']);
    $this->assertStringNotContainsString('SECRET', $preview->getContent());

    $this->assertNotEmpty($this->http->requestsTo('GET', 'https://redirect.example/'));
    $this->assertNothingPrivateWasRequested();
  }

  public function testAllowListedPrivateHostCanBeFetched() {
    config(['aperture.http.allow_private' => ['10.0.0.0/8']]);
    $token = $this->microsubToken($this->user);

    $preview = $this->microsubGet($this->user, $token, ['action' => 'preview', 'url' => 'http://10.0.0.5/']);
    $this->assertStringContainsString('SECRET', $preview->getContent());

    // Only what was listed is opened up
    $this->microsubGet($this->user, $token, ['action' => 'preview', 'url' => 'http://192.168.1.1/admin']);
    $this->assertCount(0, $this->http->requestsTo('GET', 'http://192.168.1.1/'));
  }

  public function testTokenIsNotVerifiedAtAPrivateTokenEndpoint() {
    $this->user->token_endpoint = 'http://169.254.169.254/latest/meta-data/';
    $this->user->save();

    $response = $this->withHeaders(['Authorization' => 'Bearer some-token'])->get('/microsub/'.$this->user->id.'?action=channels');
    $response->assertStatus(403);
    $this->assertStringNotContainsString('SECRET', $response->getContent());
    $this->assertCount(0, $this->http->requests);
  }

  public function testMicropubConfigIsNotLoadedFromAPrivateEndpoint() {
    $this->user->micropub_endpoint = 'http://10.0.0.5/micropub';
    $this->user->reload_micropub_config('token');
    $this->assertCount(0, $this->http->requests);
  }

  public function testApiRegistrationDoesNotCallPrivateAddresses() {
    config(['aperture.public_access' => true]);

    $this->post('/api/register', ['site' => 'https://user.example/', 'code' => 'abc', 'verification_endpoint' => 'http://127.0.0.1:6379/']);
    $this->post('/api/register', ['site' => 'http://internal.example/', 'code' => 'abc', 'verification_endpoint' => 'https://redirect.example/']);

    $this->assertNothingPrivateWasRequested();
    $this->assertCount(0, $this->http->requestsTo('POST', 'http://127.0.0.1'));
  }

  public function testLoginDoesNotDiscoverEndpointsAtAPrivateAddress() {
    config(['aperture.public_access' => true]);

    $this->post('/login', ['url' => 'http://internal.example/'])->assertRedirect();
    $this->post('/login', ['url' => 'http://127.0.0.1/'])->assertRedirect();
    $this->post('/login', ['url' => 'https://redirect.example/'])->assertRedirect();

    $this->assertNothingPrivateWasRequested();
  }

  public function testTheIndieAuthClientUsesTheGuardedHttpClient() {
    $this->assertTrue(\IndieAuth\Client::$http->safe_mode());

    // The provider sets it up that way too, not just the test case
    \IndieAuth\Client::$http = null;
    (new AppServiceProvider($this->app))->boot();
    $this->assertTrue(\IndieAuth\Client::$http->safe_mode());
  }

  public function testTheDefaultHttpClientHasSafeModeOn() {
    (new AppServiceProvider($this->app))->register();

    $http = app(\p3k\HTTP::class);
    $this->assertTrue($http->safe_mode());

    $response = $http->get('http://127.0.0.1:9/');
    $this->assertEquals('blocked_url', $response['error']);

    $this->assertArrayHasKey('error', app(\p3k\HTTP\Guard::class)->check('http://127.0.0.1/'));
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('privateUrls')]
  public function testFeedPollingRefusesPrivateAddresses($url) {
    $this->dns['localhost'] = ['127.0.0.1'];
    $source = $this->createSource($this->user, ['url' => $url]);
    $this->channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    FetchSource::dispatchSync($source->id);
    $source->refresh();

    $this->assertCount(0, $this->http->requests);
    $this->assertEquals(0, Entry::count());
    // It counts as a failed fetch, so the source backs off like any other broken feed
    $this->assertEquals(1, $source->consecutive_failures);
    $this->assertEquals(0, $source->last_http_status);
    $this->assertNotNull($source->next_check_at);
  }

  public function testFeedThatRedirectsToAPrivateAddressIsNotFollowed() {
    $source = $this->createSource($this->user, ['url' => 'https://redirect.example/']);
    $this->channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    FetchSource::dispatchSync($source->id);

    $this->assertNothingPrivateWasRequested();
    $this->assertEquals(0, Entry::count());
    $this->assertEquals(1, $source->fresh()->consecutive_failures);
  }

  public function testNoCodeMakesRequestsAroundTheGuard() {
    // Requests have to go through app(\p3k\HTTP::class) or SafeDownload. Anything that
    // builds its own client or calls curl directly would skip the address checks.
    $allowed = ['Services/SafeDownload.php', 'Providers/AppServiceProvider.php'];
    $patterns = ['/\bcurl_(init|exec|multi_init)\b/', '/new\s+\\\\?(p3k\\\\)?HTTP\s*\(/', '/new\s+\\\\?GuzzleHttp/', '/\bHttp::/', '/\bfsockopen\b/', '/set_safe_mode\s*\(\s*false/', "/'safe_mode'\s*=>\s*false/"];

    $offenders = [];
    foreach(\Illuminate\Support\Facades\File::allFiles(app_path()) as $file) {
      if(in_array($file->getRelativePathname(), $allowed))
        continue;
      foreach($patterns as $pattern) {
        if(preg_match($pattern, $file->getContents()))
          $offenders[] = $file->getRelativePathname().' matches '.$pattern;
      }
    }
    $this->assertEquals([], $offenders);
  }

  // ---- media downloads, which don't go through the HTTP client

  private function fakeDownload() {
    $download = new FakeDownload(app(\p3k\HTTP\Guard::class));
    $this->app->instance(SafeDownload::class, $download);
    return $download;
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('privateUrls')]
  public function testMediaIsNotDownloadedFromPrivateAddresses($url) {
    $this->dns['localhost'] = ['127.0.0.1'];
    Storage::fake('local');
    config(['aperture.media_url' => 'https://media.aperture.test']);
    $download = $this->fakeDownload();
    $download->responses[$url.'secret.png'] = ['code' => 200, 'body' => base64_decode(self::PNG)];

    $this->assertFalse(Media::createFromURL($url.'secret.png'));

    $this->assertCount(0, $download->hops);
    $this->assertEquals(0, Media::count());
    $this->assertEquals([], Storage::disk('local')->allFiles());
  }

  public function testMediaDownloadRefusesOtherSchemes() {
    $download = $this->fakeDownload();
    foreach(['file:///etc/passwd', 'gopher://93.184.216.34/', 'ftp://files.example/a.png', 'dict://127.0.0.1:11211/stats', '/etc/passwd'] as $url) {
      $path = tempnam(sys_get_temp_dir(), 'ssrf');
      $result = $download->toFile($url, $path);
      $this->assertEquals('blocked_url', $result['error'], $url);
      $this->assertEquals(0, filesize($path));
      unlink($path);
    }
    $this->assertCount(0, $download->hops);
  }

  public function testMediaDownloadChecksEveryRedirect() {
    Storage::fake('local');
    config(['aperture.media_url' => 'https://media.aperture.test']);
    $download = $this->fakeDownload();
    $download->responses['https://cdn.example/a.png'] = ['code' => 302, 'location' => '/b.png', 'body' => 'redirecting'];
    $download->responses['https://cdn.example/b.png'] = ['code' => 301, 'location' => 'http://169.254.169.254/latest/meta-data/'];
    $download->responses['http://169.254.169.254/latest/meta-data/'] = ['code' => 200, 'body' => 'SECRET'];

    $this->assertFalse(Media::createFromURL('https://cdn.example/a.png'));

    $this->assertEquals(['https://cdn.example/a.png', 'https://cdn.example/b.png'], array_column($download->hops, 'url'));
    $this->assertEquals(0, Media::count());
    $this->assertEquals([], Storage::disk('local')->allFiles());
  }

  public function testMediaDownloadFollowsPublicRedirectsAndPinsTheCheckedAddress() {
    Storage::fake('local');
    config(['aperture.media_url' => 'https://media.aperture.test']);
    $download = $this->fakeDownload();
    $download->responses['https://cdn.example/a.png'] = ['code' => 302, 'location' => 'https://images.example:8443/b.png'];
    $download->responses['https://images.example:8443/b.png'] = ['code' => 200, 'body' => base64_decode(self::PNG)];

    $media = Media::createFromURL('https://cdn.example/a.png');

    $this->assertInstanceOf(Media::class, $media);
    $this->assertEquals(['cdn.example:443:93.184.216.34'], $download->hops[0]['resolve']);
    $this->assertEquals(['images.example:8443:93.184.216.34'], $download->hops[1]['resolve']);
    $this->assertCount(1, Storage::disk('local')->allFiles());
  }

  public function testMediaDownloadStopsAfterTooManyRedirects() {
    $download = $this->fakeDownload();
    for($i = 0; $i < 20; $i++)
      $download->responses['https://cdn.example/'.$i] = ['code' => 302, 'location' => '/'.($i + 1)];

    $path = tempnam(sys_get_temp_dir(), 'ssrf');
    $result = $download->toFile('https://cdn.example/0', $path);
    unlink($path);

    $this->assertEquals('too_many_redirects', $result['error']);
    $this->assertCount(9, $download->hops);
  }

  public function testEntryPhotosOnPrivateAddressesAreLeftAlone() {
    Storage::fake('local');
    config(['aperture.media_url' => 'https://media.aperture.test']);
    $download = $this->fakeDownload();
    $download->responses['http://10.0.0.5/photo.png'] = ['code' => 200, 'body' => base64_decode(self::PNG)];

    $source = $this->createSource($this->user, ['download_images' => 1]);
    $entry = $this->createEntry($source, [
      'photo' => ['http://10.0.0.5/photo.png'],
      'content' => ['text' => 'x', 'html' => '<p><img src="http://169.254.169.254/latest/meta-data/"></p>'],
      'author' => ['type' => 'card', 'name' => 'A', 'photo' => 'http://internal.example/me.jpg'],
    ]);
    event(new EntrySaved($entry));

    $this->assertCount(0, $download->hops);
    $this->assertEquals(0, Media::count());
    $data = json_decode($entry->fresh()->data, true);
    $this->assertEquals(['http://10.0.0.5/photo.png'], $data['photo']);
    $this->assertEquals('http://internal.example/me.jpg', $data['author']['photo']);
    $this->assertArrayNotHasKey('refs', $data);
  }

  public function testImageMetadataIsOnlyReadFromBitmapImages() {
    config(['aperture.media_url' => 'https://media.aperture.test']);
    $download = $this->fakeDownload();
    $download->responses['https://cdn.example/real.png'] = ['code' => 200, 'body' => base64_decode(self::PNG)];
    // An SVG that refers to another file: Imagick would try to load it
    $download->responses['https://cdn.example/trick.svg'] = ['code' => 200, 'body' => '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="40" height="20"><image xlink:href="file:///etc/passwd" width="40" height="20"/></svg>'];
    $download->responses['https://cdn.example/trick.mvg'] = ['code' => 200, 'body' => "push graphic-context\nviewbox 0 0 40 20\nimage over 0,0 0,0 'url(http://127.0.0.1/)'\npop graphic-context\n"];
    $download->responses['http://10.0.0.5/photo.png'] = ['code' => 200, 'body' => base64_decode(self::PNG)];

    // Images aren't downloaded for this source, so only their dimensions are looked up.
    // The listener logs whether it could read them.
    $logged = [];
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Log\Events\MessageLogged::class, function($event) use(&$logged) {
      $logged[] = $event->message;
    });
    $source = $this->createSource($this->user, ['download_images' => 0]);
    foreach(['https://cdn.example/real.png', 'https://cdn.example/trick.svg', 'https://cdn.example/trick.mvg', 'http://10.0.0.5/photo.png'] as $photo) {
      event(new EntrySaved($this->createEntry($source, ['photo' => [$photo]])));
    }

    $this->assertContains('Found dimensions of image: https://cdn.example/real.png 1', $logged);
    // The files that aren't bitmaps are turned away before Imagick sees them
    $this->assertContains('Not reading metadata for https://cdn.example/trick.svg because it is not a JPEG, PNG, GIF or WebP image', $logged);
    $this->assertContains('Not reading metadata for https://cdn.example/trick.mvg because it is not a JPEG, PNG, GIF or WebP image', $logged);
    $this->assertEquals([], preg_grep('/^Failed to get metadata for /', $logged));
    $this->assertContains('Failed to get dimension of image: https://cdn.example/trick.svg', $logged);
    $this->assertContains('Failed to get dimension of image: https://cdn.example/trick.mvg', $logged);
    $this->assertContains('Failed to get dimension of image: http://10.0.0.5/photo.png', $logged);
    $this->assertEquals([], preg_grep('/^Found dimensions of image: (?!https:\/\/cdn.example\/real.png)/', $logged));

    $this->assertEquals(['https://cdn.example/real.png', 'https://cdn.example/trick.svg', 'https://cdn.example/trick.mvg'], array_column($download->hops, 'url'));
  }
}
