<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Events\SourceRemoved;

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

  public function remove_source(Source $source) {
    $this->sources()->detach($source->id);
    event(new SourceRemoved($source, $this));
  }

  public function delete() {
    $sources = $this->sources()->get();
    foreach($sources as $source) {
      $this->remove_source($source);
    }
    parent::delete();
  }

}
