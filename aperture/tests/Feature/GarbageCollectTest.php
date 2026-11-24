<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Entry;
use DB;

class GarbageCollectTest extends TestCase
{
  use RefreshDatabase;

  public function testDeletesOnlyOrphanedEntriesThatLeftTheFeed() {
    $user = $this->createUser();
    $channel = $this->homeChannel($user);
    $source = $this->createSource($user);

    $orphan = $this->createEntry($source);
    $inChannel = $this->createEntry($source);
    $this->addToChannel($channel, $inChannel);
    $stillInFeed = $this->createEntry($source, [], ['currently_in_feed' => true]);

    $this->artisan('data:garbagecollect')->assertExitCode(0);

    $this->assertNull(Entry::find($orphan->id));
    $this->assertNotNull(Entry::find($inChannel->id));
    $this->assertNotNull(Entry::find($stillInFeed->id));
  }

  // Deleting rows while paging through them with an offset skips every second page
  public function testDeletesEveryOrphanedEntryInOneRun() {
    $user = $this->createUser();
    $source = $this->createSource($user);

    $rows = [];
    for($i = 0; $i < 2500; $i++) {
      $rows[] = [
        'source_id' => $source->id,
        'unique' => 'entry-'.$i,
        'data' => '{"type":"entry"}',
        'currently_in_feed' => false,
        'created_at' => '2018-01-01 12:00:00',
        'updated_at' => '2018-01-01 12:00:00',
      ];
    }
    foreach(array_chunk($rows, 500) as $chunk) {
      DB::table('entries')->insert($chunk);
    }

    $this->artisan('data:garbagecollect')->assertExitCode(0);

    $this->assertEquals(0, Entry::count());
  }

  // Entries posted to an API key source never appear in a feed delivery, so nothing
  // would ever clear the flag that was set on them when the column was introduced
  public function testMigrationClearsInFeedFlagForApiKeySourcesOnly() {
    $user = $this->createUser();
    $apikey = $this->createSource($user, ['format' => 'apikey', 'url' => '']);
    $feed = $this->createSource($user);

    $stuck = $this->createEntry($apikey, [], ['currently_in_feed' => true]);
    $inFeed = $this->createEntry($feed, [], ['currently_in_feed' => true]);

    $migration = require database_path('migrations/2026_11_24_000000_clear_in_feed_flag_for_apikey_entries.php');
    $migration->up();

    $this->assertEquals(0, $stuck->fresh()->currently_in_feed);
    $this->assertEquals(1, $inFeed->fresh()->currently_in_feed);

    // With the flag cleared, the orphaned entry is now collected
    $this->artisan('data:garbagecollect')->assertExitCode(0);
    $this->assertNull(Entry::find($stuck->id));
    $this->assertNotNull(Entry::find($inFeed->id));
  }
}
