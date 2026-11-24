<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

  /**
   * Start polling a stopped source again, with a clean record: it gets another full
   * period of failures before it would be stopped again. The caller fetches it.
   */
  public function resume() {
    $timestamps = $this->timestamps;
    $this->timestamps = false;
    $this->stopped_at = null;
    $this->failing_since = null;
    $this->consecutive_failures = 0;
    $this->fetch_queued_at = null;
    $this->next_check_at = null;
    $this->save();
    $this->timestamps = $timestamps;
  }

  public function to_array() {
    $name = $this->name ?: \p3k\url\display_url($this->url);
    return [
      'name' => $name
    ];
  }

}
