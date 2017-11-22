<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model {

  protected $fillable = [
    'source_id', 'url', 'data'
  ];

  public function source() {
    return $this->belongsTo('\App\Source');
  }

  public function to_array() {
    return json_decode($this->data, true);
  }

}
