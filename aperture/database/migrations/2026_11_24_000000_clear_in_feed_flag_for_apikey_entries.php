<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Entries that existed when the currently_in_feed column was introduced were
     * flagged as being in their feed. The flag is only ever cleared by a feed
     * delivery, and API key (Micropub) sources never receive one, so those
     * entries were never eligible for garbage collection.
     *
     * This only clears the flag. data:garbagecollect deletes the entries that
     * are no longer in any channel, along with their media files.
     */
    public function up(): void
    {
        DB::table('entries')
            ->whereIn('source_id', DB::table('sources')->select('id')->where('format', 'apikey'))
            ->where('currently_in_feed', true)
            ->update(['currently_in_feed' => false]);
    }

    /**
     * The previous values were wrong and can't be reconstructed.
     */
    public function down(): void
    {
        //
    }
};
