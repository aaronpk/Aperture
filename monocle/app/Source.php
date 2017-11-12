<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Source extends Model {

  protected $fillable = [
    'token', 'url', 'format', 'websub', 'created_by'
  ];

  public function entries() {
    return $this->hasMany('App\Entry');
  }

  public function sources() {
    return $this->belongsToMany('\App\Channel');
  }

}
