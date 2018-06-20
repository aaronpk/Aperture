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

  public function channels() {
    return $this->belongsToMany('\App\Channel');
  }

  public function subscribe() {
    $http = new \p3k\HTTP();
    $response = $http->post(env('WATCHTOWER_URL'), http_build_query([
      'hub.mode' => 'subscribe',
      'hub.topic' => $this->url,
      'hub.callback' => env('WATCHTOWER_CB').'/websub/source/'.$this->token
    ]), [
      'Authorization: Bearer '.env('WATCHTOWER_TOKEN')
    ]);
    Log::info("Subscribed\n".$response['body']);
  }

  public function unsubscribe() {
    $http = new \p3k\HTTP();
    $response = $http->post(env('WATCHTOWER_URL'), http_build_query([
      'hub.mode' => 'unsubscribe',
      'hub.topic' => $this->url,
      'hub.callback' => env('WATCHTOWER_CB').'/websub/source/'.$this->token
    ]), [
      'Authorization: Bearer '.env('WATCHTOWER_TOKEN')
    ]);
  }

}
