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

  public function to_array() {
    $data = json_decode($this->data, true);
    unset($data['uid']);
    return $data;
  }

}
