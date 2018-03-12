<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Media extends Model {

  protected $fillable = [
    'entry_id', 'filename'
  ];

  public function entru() {
    return $this->belongsTo('\App\Entry');
  }

}
