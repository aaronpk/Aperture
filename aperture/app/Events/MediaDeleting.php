<?php
namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use App\Media;
use Log;

class MediaDeleting
{
  use Dispatchable, SerializesModels;

  public $file;

  public function __construct(Media $file)
  {
    $this->file = $file;
  }
}
