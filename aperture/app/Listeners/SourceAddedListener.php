<?php

namespace App\Listeners;

use App\Events\SourceAdded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log;

class SourceAddedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  SourceAdded  $event
     * @return void
     */
    public function handle(SourceAdded $event)
    {
        Log::info("Source added: ".$event->source->url." to channel: ".$event->channel->name);
        $channels = $event->source->channels();
        Log::info("Source now belongs to ".$channels->count()." channels");

        // If this was a newly added source (belongs to just one channel), subscribe to updates
        if($event->source->url && $channels->count() == 1) {
            $event->source->subscribe();
        }

        // Add any existing entries to this channel
        Log::info("This source has ".$event->source->entries()->count()." existing entries. Adding to channel ".$event->channel->id);
        $added = 0;
        if($event->source->entries()->count()) {
            foreach($event->source->entries()->orderByDesc('created_at')->get() as $i=>$entry) {
                if(!$event->channel->entries()->where('entry_id', $entry->id)->first()) {
                    $shouldAdd = $event->channel->should_add_entry($entry);
                    if($shouldAdd) {

                      $created_at = $entry->published ?: date('Y-m-d H:i:s');
                      if(strtotime($created_at) <= 0) $created_at = '1970-01-01 00:00:01';

                      $event->channel->entries()->attach($entry->id, [
                        'created_at' => $created_at,
                        'seen' => 1,
                        'batch_order' => $i,
                      ]);
                      $added++;
                    }
                }
            }
        }
        Log::info("Added $added matching entries to channel");
    }
}
