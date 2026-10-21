<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Source;

class WebPagesTest extends TestCase
{
  use RefreshDatabase;

  public function testPublicPages() {
    $this->get('/')->assertStatus(200)->assertSee('<link rel="micropub" href="http://aperture.test/micropub">', false);
    $this->get('/docs')->assertStatus(200)->assertSee('POST http://aperture.test/micropub');
    $this->get('/pricing')->assertStatus(200);
    $this->get('/login')->assertStatus(200);
  }

  public function testGuestsAreRedirectedToLogin() {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/settings')->assertRedirect('/login');
  }

  public function testDashboard() {
    $user = $this->createUser();
    $this->actingAs($user)->get('/dashboard')
      ->assertStatus(200)
      ->assertSee('Home')
      ->assertSee(htmlspecialchars('<link rel="microsub" href="http://aperture.test/microsub/'.$user->id.'">'), false);
  }

  public function testChannelPageAndPermissions() {
    $user = $this->createUser();
    $other = $this->createUser(['url' => 'https://other.example/']);
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user, ['url' => 'https://blog.example/']);
    $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);

    $this->actingAs($user)->get('/channel/'.$channel->id)->assertStatus(200)->assertSee('blog.example');
    $this->actingAs($other)->get('/channel/'.$channel->id)->assertStatus(401);
  }

  public function testCreateAndSaveChannel() {
    $user = $this->createUser();
    $this->actingAs($user)->post('/channel/new', ['name' => 'From the web'])->assertRedirect('/dashboard');
    $channel = $user->channels()->where('name', 'From the web')->first();
    $this->assertNotNull($channel);

    $this->actingAs($user)->post('/channel/'.$channel->id.'/save', [
      'name' => 'Renamed', 'include_only' => 'articles', 'exclude_keywords' => 'foo bar', 'read_tracking_mode' => 'boolean',
    ])->assertStatus(200);
    $channel->refresh();
    $this->assertEquals('Renamed', $channel->name);
    $this->assertEquals('articles', $channel->include_only);
    $this->assertEquals('foo bar', $channel->exclude_keywords);
  }

  public function testAddSourceFromDashboard() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $this->actingAs($user)->post('/channel/'.$channel->id.'/add_source', ['url' => 'https://blog.example/', 'format' => 'microformats'])
      ->assertStatus(200)->assertExactJson(['result' => 'ok']);
    $this->assertEquals(1, $channel->sources()->where('url', 'https://blog.example/')->count());
    $this->assertCount(1, $this->http->requestsTo('POST', 'https://watchtower.test/'));
  }

  public function testSettings() {
    $user = $this->createUser();
    $this->actingAs($user)->get('/settings')->assertStatus(200);
    $this->actingAs($user)->post('/settings/save', ['demo_mode_enabled' => 'on'])->assertRedirect('/settings');
    $this->assertEquals(1, $user->fresh()->demo_mode_enabled);
  }
}
