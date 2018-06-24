<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MediaEntryIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('entry_media', function (Blueprint $table) {
            $table->index(['media_id', 'entry_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('entry_media', function (Blueprint $table) {
            $table->dropIndex('entry_media_media_id_entry_id_index');
        });
    }
}
