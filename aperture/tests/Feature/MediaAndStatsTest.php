<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;

class MediaAndStatsTest extends TestCase
{
  use RefreshDatabase;

  public function testMediaUploadIsStoredUnderMediaDirectory() {
    Storage::fake('local');
    config(['aperture.media_url' => 'https://media.aperture.test']);

    $user = $this->createUser();
    $source = $this->createSource($user, ['token' => 'media-token']);

    // 1x1 transparent PNG
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $file = UploadedFile::fake()->createWithContent('pixel.png', $png);
    $hash = hash('sha256', $png);

    $response = $this->withHeaders(['Authorization' => 'Bearer media-token'])
      ->post('/micropub/media', ['file' => $file]);
    $response->assertStatus(201);

    $url = 'https://media.aperture.test/aperture.test/'.$hash.'.png';
    $response->assertExactJson(['url' => $url]);
    $this->assertEquals($url, $response->headers->get('Location'));

    Storage::disk('local')->assertExists('media/aperture.test/'.$hash.'.png');
    $this->assertEquals(1, Media::where('hash', $hash)->count());

    $this->withHeaders(['Authorization' => 'Bearer media-token'])->post('/micropub/media')
      ->assertStatus(400)->assertJson(['error' => 'invalid_request']);
  }

  public function testLocalDiskRootIsStorageApp() {
    // Production media lives in storage/app/media, not Laravel 11+'s storage/app/private
    $this->assertEquals(storage_path('app'), config('filesystems.disks.local.root'));
  }

  public function testLocalDiskCreatesWorldReadableMedia() {
    $root = sys_get_temp_dir().'/aperture-disk-test-'.bin2hex(random_bytes(4));
    $disk = Storage::build(array_merge(config('filesystems.disks.local'), ['root' => $root]));

    $disk->makeDirectory('media/example.com');
    $disk->put('media/example.com/file.png', fopen('data://text/plain,hello', 'r'));

    $this->assertEquals('0755', substr(sprintf('%o', fileperms($root.'/media/example.com')), -4));
    $this->assertEquals('0644', substr(sprintf('%o', fileperms($root.'/media/example.com/file.png')), -4));

    (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($root);
  }

  public function testEntryCounterUsesUnprefixedRedisKey() {
    $key = config('app.url').'::entries';
    Redis::set($key, 10);

    $user = $this->createUser();
    $entry = $this->createEntry($this->createSource($user));
    $this->assertEquals(11, Redis::get($key));

    $entry->delete();
    $this->assertEquals(10, Redis::get($key));

    // The stats endpoint reads the same key
    config(['aperture.munin_token' => 'munin']);
    $this->get('/stats/entries?token=munin')->assertStatus(200)->assertSeeText('entries.value 10');
    Redis::del($key);
  }

  public function testStatsRequireMuninToken() {
    config(['aperture.munin_token' => 'munin']);
    $this->createUser();

    $this->get('/stats/users')->assertStatus(401);
    $this->get('/stats/users?token=munin')->assertStatus(200)->assertSeeText('users.value 1');
    $this->get('/stats/users?token=munin&mode=config')->assertStatus(200)->assertSeeText('graph_title Aperture Users');
    $this->get('/stats/new_entries?token=munin')->assertStatus(200)->assertSeeText('new_entries.value');

    // The image proxy is no longer used, so there is nothing to graph for it
    $this->get('/stats/proxy_bytes?token=munin')->assertStatus(404);
  }
}
