<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

use App\Source, App\Channel;

class SourceAdded
{
    use Dispatchable, SerializesModels;

    public $source;
    public $channel;

    public function __construct(Source $source, Channel $channel)
    {
        $this->source = $source;
        $this->channel = $channel;
    }
}
