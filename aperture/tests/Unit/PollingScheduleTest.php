<?php
namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Carbon;
use App\Models\Source;
use App\Services\PollingSchedule;

class PollingScheduleTest extends TestCase
{
  private $now;

  protected function setUp(): void {
    parent::setUp();
    $this->now = Carbon::parse('2026-11-01 12:00:00');
  }

  private function source(array $attrs=[]) {
    $source = new Source();
    $source->forceFill(array_merge([
      'entries_per_day' => 0,
      'consecutive_failures' => 0,
      'last_new_entry_at' => null,
      'last_checked_at' => null,
      'created_at' => '2020-01-01 00:00:00',
    ], $attrs));
    return $source;
  }

  private function interval(array $attrs) {
    return PollingSchedule::interval($this->source($attrs), $this->now);
  }

  public function testTierFollowsNewEntriesPerDay() {
    $this->assertEquals(5, $this->interval(['entries_per_day' => 10]));
    $this->assertEquals(5, $this->interval(['entries_per_day' => 250]));
    $this->assertEquals(15, $this->interval(['entries_per_day' => 9.99]));
    $this->assertEquals(15, $this->interval(['entries_per_day' => 1]));
  }

  public function testTierFollowsTimeSinceLastNewEntry() {
    $this->assertEquals(60, $this->interval(['entries_per_day' => 0.99, 'last_new_entry_at' => '2026-11-01 11:00:00']));
    $this->assertEquals(60, $this->interval(['last_new_entry_at' => '2026-10-02 12:00:00']));   // 30 days
    $this->assertEquals(360, $this->interval(['last_new_entry_at' => '2026-10-02 11:59:59']));
    $this->assertEquals(360, $this->interval(['last_new_entry_at' => '2025-11-01 12:00:00']));  // 365 days
    $this->assertEquals(1440, $this->interval(['last_new_entry_at' => '2025-11-01 11:59:59']));
    $this->assertEquals(1440, $this->interval(['last_new_entry_at' => null]));
  }

  public function testRecentlyAddedSourceIsCheckedHourly() {
    $this->assertEquals(60, $this->interval(['created_at' => '2026-10-20 00:00:00']));
    $this->assertEquals(60, $this->interval(['created_at' => null]));
    $this->assertEquals(1440, $this->interval(['created_at' => '2026-09-01 00:00:00']));
  }

  public function testFailingSourcesAreCheckedLessOften() {
    $busy = ['entries_per_day' => 50, 'last_new_entry_at' => '2026-11-01 11:00:00'];
    $this->assertEquals(5, $this->interval($busy + ['consecutive_failures' => 4]));
    $this->assertEquals(360, $this->interval($busy + ['consecutive_failures' => 5]));
    $this->assertEquals(360, $this->interval($busy + ['consecutive_failures' => 29]));
    $this->assertEquals(1440, $this->interval($busy + ['consecutive_failures' => 30]));
    // Failures never make a slow source faster
    $this->assertEquals(1440, $this->interval(['consecutive_failures' => 5]));
  }

  public function testEntriesPerDayIsAMovingAverage() {
    // A first check that finds 7 entries counts as one a day over the 7 day window
    $this->assertEqualsWithDelta(1.0, PollingSchedule::entriesPerDay($this->source(), 7, $this->now), 0.0001);

    // With nothing new, the average decays to 1/e of its value over 7 days
    $source = $this->source(['entries_per_day' => 10, 'last_checked_at' => '2026-10-25 12:00:00']);
    $this->assertEqualsWithDelta(10 / M_E, PollingSchedule::entriesPerDay($source, 0, $this->now), 0.0001);

    // A source that posts steadily settles at its real rate: 24 a day, checked hourly
    $source = $this->source(['entries_per_day' => 0]);
    $at = $this->now->copy();
    for($i = 0; $i < 24 * 60; $i++) {
      $source->entries_per_day = PollingSchedule::entriesPerDay($source, 1, $at);
      $source->last_checked_at = $at->copy();
      $at->addHour();
    }
    $this->assertEqualsWithDelta(24, $source->entries_per_day, 0.5);
  }

  public function testNextCheckIsWithinTenPercentOfTheInterval() {
    for($i = 0; $i < 200; $i++) {
      $seconds = $this->now->diffInSeconds(PollingSchedule::nextCheckAt(60, $this->now));
      $this->assertGreaterThanOrEqual(3240, $seconds);
      $this->assertLessThanOrEqual(3960, $seconds);
    }
  }
}
