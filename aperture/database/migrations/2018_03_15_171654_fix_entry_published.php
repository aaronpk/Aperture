<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Entry;

class FixEntryPublished extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $entries = Entry::where('published', '1970-01-01 00:00:00')->get();
        foreach($entries as $entry) {
          $data = json_decode($entry->data, true);
          if(isset($data['published']) && preg_match('/ZZ/', $data['published'])) {
            $published = str_replace('ZZ', 'Z', $data['published']);
            $data['published'] = $published;
            Log::info("Fixing published date for entry ".$entry->id.": ".$published);
            $entry->data = json_encode($data, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES);
            $entry->published = date('Y-m-d H:i:s', strtotime($published));
            $entry->save();
          }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
