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

}
