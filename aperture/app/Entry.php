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

    if($channel) {
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

}
