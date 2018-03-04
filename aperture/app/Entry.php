<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class Entry extends Model {

  protected $fillable = [
    'source_id', 'unique', 'data'
  ];

  public function source() {
    return $this->belongsTo('\App\Source');
  }

  public function channels() {
    return $this->belongsToMany('\App\Channel');
  }

  public function permalink() {
    return env('APP_URL').'/entry/'.$this->source->id.'/'.$this->unique;
  }

  public function to_array($channel=false) {
    $data = json_decode($this->data, true);
    unset($data['uid']); // don't include mf2 uid in the response

    // Include some Microsub info
    $data['_id'] = (string)$this->id;

    if($channel && $channel->read_tracking_mode != 'disabled') {
      $ce = DB::table('channel_entry')
        ->where('channel_id', $channel->id)
        ->where('entry_id', $this->id)
        ->first();
      $data['_is_read'] = (bool)$ce->seen;
    }

    // For testing, override the JSON published with the DB published
    if(env('APP_ENV') == 'testing')
      $data['published'] = date('c', strtotime($this->published));

    return $data;
  }

  public function matches_keyword($keyword) {
    $data = json_decode($this->data, true);

    $matches = false;

    // Check the name, content.text, and category values for a keyword match

    if(isset($data['name'])) {
      if(stripos($data['name'], $keyword) !== false) {
        $matches = true;
      }
    }

    if(!$matches && isset($data['content'])) {
      if(stripos($data['content']['text'], $keyword) !== false) {
        $matches = true;
      }
    }

    if(!$matches && isset($data['category'])) {
      foreach($data['category'] as $c) {
        if(strtolower($c) == strtolower($keyword)) {
          $matches = true;
        }
      }
    }

    return $matches;
  }

  public function post_type() {
    $data = json_decode($this->data, true);

    // Implements Post Type Discovery
    // https://www.w3.org/TR/post-type-discovery/#algorithm

    if($data['type'] == 'event')
      return 'event';

    // Experimental
    if($data['type'] == 'card')
      return 'card';

    // Experimental
    if($data['type'] == 'review')
      return 'review';

    // Experimental
    if($data['type'] == 'recipe')
      return 'recipe';

    if(isset($data['rsvp']))
      return 'rsvp';

    if(isset($data['repost-of']))
      return 'repost';

    if(isset($data['like-of']))
      return 'like';

    if(isset($data['in-reply-to']))
      return 'reply';

    // Experimental
    if(isset($data['bookmark-of']))
      return 'bookmark';

    // Experimental
    if(isset($data['checkin']))
      return 'checkin';

    if(isset($data['video']))
      return 'video';

    if(isset($data['photo']))
      return 'photo';

    // XRay has already done the content/name normalization

    if(isset($data['name']))
      return 'article';

    return 'note';
  }

}
