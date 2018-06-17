<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage;
use App\Events\MediaDeleting;

class Media extends Model {

  protected $fillable = [
    'entry_id', 'filename'
  ];

  protected $dispatchesEvents = [
    'deleting' => MediaDeleting::class
  ];

  public function entries() {
    return $this->belongsToMany('\App\Entry');
  }

  public function url() {
    return env('MEDIA_URL').'/'.$this->filename;
  }

}
