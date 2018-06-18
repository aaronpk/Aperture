<?php
namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use App\Entry;
use Log;

class EntryCreating
{
  use Dispatchable, SerializesModels;

  public $entry;

  public function __construct(Entry $entry)
  {
    $this->entry = $entry;
  }
}
