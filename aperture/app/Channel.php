<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Events\SourceRemoved;
use DB;

class Channel extends Model {

  protected $fillable = [
    'name', 'icon', 'sparkline'
  ];

  public function user() {
    return $this->belongsTo('\App\User');
  }

  public function sources() {
    return $this->belongsToMany('\App\Source');
  }

  public function entries() {
    return $this->belongsToMany('\App\Entry');
  }

  public function to_array() {
    return [
      'uid' => $this->uid,
      'name' => $this->name,
      'unread' => $this->entries()->where('seen', 0)->count(),
    ];
  }

  public function remove_source(Source $source, $remove_entries=false) {
    if($remove_entries) {
      DB::table('channel_entry')
        ->join('entries', 'channel_entry.entry_id', '=', 'entries.id')
        ->where('channel_entry.channel_id', $this->id)
        ->where('entries.source_id', $source->id)
        ->delete();
    }
    $this->sources()->detach($source->id);
    event(new SourceRemoved($source, $this));
  }

  public function delete() {
    $sources = $this->sources()->get();
    foreach($sources as $source) {
      $this->remove_source($source);
    }
    parent::delete();
  }

  public function mark_entries_read(array $entry_ids) {
    return DB::table('channel_entry')
      ->where('channel_id', $this->id)
      ->whereIn('entry_id', $entry_ids)
      ->update(['seen' => 1]);
  }

  public function mark_entries_unread(array $entry_ids) {
    return DB::table('channel_entry')
      ->where('channel_id', $this->id)
      ->whereIn('entry_id', $entry_ids)
      ->update(['seen' => 0]);
  }

  public function mark_entries_read_before(Entry $entry, $channel_entry) {
    // TODO: Need some other method for sorting entries since the entry published date is used 
    // to sort when returning items in the timeline.
    // Hoping that sorting by ID in the channel_entry table will be close enough to resolve conflicts.
    return DB::table('channel_entry')
      ->where('channel_id', $this->id)
      ->where('id', '<=', $channel_entry->id)
      ->update(['seen' => 1]);
  }

}
