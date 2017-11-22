<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

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
    ];
  }

}
