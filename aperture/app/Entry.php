<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model {

  protected $fillable = [
    'source_id', 'unique', 'data'
  ];

  public function source() {
    return $this->belongsTo('\App\Source');
  }

  public function permalink() {
    return env('APP_URL').'/entry/'.$this->source->id.'/'.$this->unique;
  }

  public function to_array() {
    $data = json_decode($this->data, true);
    unset($data['uid']); // don't include mf2 uid in the response

    // Include some Microsub info
    $data['_id'] = (string)$this->id;
    $data['_is_read'] = (bool)$this->seen;

    // For testing, override the JSON published with the DB published
    if(env('APP_ENV') == 'testing') 
      $data['published'] = date('c', strtotime($this->published));

    return $data;
  }

}
