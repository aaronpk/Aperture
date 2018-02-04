<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class ChannelToken extends Model {

  protected $fillable = [
    'token', 'channel_id', 'scope'
  ];

  public function channel() {
    return $this->belongsTo('\App\Channel');
  }

  public function scopes() {
    return explode(' ', $this->scope);
  }

}
