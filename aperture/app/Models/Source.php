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

  public function to_array() {
    $name = $this->name ?: \p3k\url\display_url($this->url);
    return [
      'name' => $name
    ];
  }

}
