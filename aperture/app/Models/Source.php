<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Log;

class Source extends Model {

  protected $fillable = [
    'token', 'url', 'format', 'websub', 'created_by', 'download_images'
  ];

  public function entries() {
    return $this->hasMany('App\Models\Entry');
  }

  public function channels() {
    return $this->belongsToMany('\App\Models\Channel')->withPivot('name');
  }

  public function subscribe() {
    $http = app(\p3k\HTTP::class);
    $response = $http->post(config('aperture.watchtower.url'), http_build_query([
      'hub.mode' => 'subscribe',
      'hub.topic' => $this->url,
      'hub.callback' => config('aperture.watchtower.callback').'/websub/source/'.$this->token
    ]), [
      'Authorization: Bearer '.config('aperture.watchtower.token')
    ]);
    Log::info("Subscribed to source:".$this->id." (".parse_url($this->url,PHP_URL_HOST).")\n".$response['body']);
  }

  public function unsubscribe() {
    $http = app(\p3k\HTTP::class);
    $response = $http->post(config('aperture.watchtower.url'), http_build_query([
      'hub.mode' => 'unsubscribe',
      'hub.topic' => $this->url,
      'hub.callback' => config('aperture.watchtower.callback').'/websub/source/'.$this->token
    ]), [
      'Authorization: Bearer '.config('aperture.watchtower.token')
    ]);
    Log::info("Unsubscribed from source:".$this->id." (".parse_url($this->url,PHP_URL_HOST).")\n".$response['body']);
  }

  public function to_array() {
    $name = $this->name ?: \p3k\url\display_url($this->url);
    return [
      'name' => $name
    ];
  }

}
