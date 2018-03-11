<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Events\SourceRemoved;
use DB;

class Channel extends Model {

  protected $fillable = [
    'name', 'icon', 'sparkline', 'read_tracking_mode', 'include_only', 'include_keywords', 'exclude_types', 'exclude_keywords'
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

  public function excluded_types() {
    $types = [];

    if($this->exclude_types) {
      $types = explode(' ', $this->exclude_types);
    }

    return $types;
  }

  public function to_array() {
    $array = [
      'uid' => $this->uid,
      'name' => $this->name,
    ];
    switch($this->read_tracking_mode) {
      case 'count':
        $array['unread'] = $this->entries()->where('seen', 0)->count();
        break;
      case 'boolean':
        $array['unread'] = $this->entries()->where('seen', 0)->count() > 0;
        break;
    }
    if($this->default_destination) {
      $array['destination'] = $this->default_destination;
    }
    return $array;
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

  public function remove_entries(array $entry_ids) {
    return  DB::table('channel_entry')
      ->where('channel_id', $this->id)
      ->whereIn('entry_id', $entry_ids)
      ->delete();
  }

  public function mark_entries_read_before(Entry $entry, $channel_entry) {
    // TODO: Need some other method for sorting entries since the entry published date is used
    // to sort when returning items in the timeline.
    return DB::table('channel_entry')
      ->where('channel_id', $this->id)
      ->where('created_at', '<=', $channel_entry->created_at)
      ->update(['seen' => 1]);
  }

  public function should_add_entry(Entry $entry) {
    $shouldAdd = true;

    // If the channel has type or keyword filters, check them now before adding
    if($this->include_only) {
      switch($this->include_only) {
        case 'photos_videos':
          // allow any post with a photo, not just photo posts. e.g. a checkin with a photo
          $shouldAdd = in_array($entry->post_type(), ['photo','video']) || $entry->has_photo(); break;
        case 'articles':
          $shouldAdd = $entry->post_type() == 'article'; break;
        case 'checkins':
          $shouldAdd = $entry->post_type() == 'checkin'; break;
      }
    }

    // at least one keyword must match to have the post included,
    // but don't let keyword whitelist override the type filter
    if($shouldAdd && $this->include_keywords) {
      $shouldAdd = false;
      $keywords = explode(' ', $this->include_keywords);
      foreach($keywords as $kw) {
        if($entry->matches_keyword($kw)) {
          $shouldAdd = true;
          break;
        }
      }
    }

    if($this->exclude_types) {
      // if the post is any one of the excluded types, reject it now
      foreach($this->excluded_types() as $type) {
        if($entry->post_type() == $type) {
          $shouldAdd = false;
          break;
        }
      }
    }

    if($this->exclude_keywords) {
      // if the post matches any of the blacklisted terms, reject it now
      $keywords = explode(' ', $this->exclude_keywords);
      foreach($keywords as $kw) {
        if($entry->matches_keyword($kw)) {
          $shouldAdd = false;
          break;
        }
      }
    }

    return $shouldAdd;
  }

}
