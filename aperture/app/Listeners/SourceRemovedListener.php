<?php

namespace App\Listeners;

use App\Events\SourceRemoved;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log;

class SourceRemovedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  SourceRemoved  $event
     * @return void
     */
    public function handle(SourceRemoved $event)
    {
        Log::info("Source removed: ".$event->source->url." from channel: ".$event->channel->name);
        $channels = $event->source->channels();
        Log::info("Source now belongs to ".$channels->count()." channels");

        // If the source no longer belongs to any channels, unsubscribe from updates
        if($channels->count() == 0) {
            $event->source->unsubscribe();
        }
    }
}
