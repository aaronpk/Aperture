<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameJoinTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('channel_sources', 'channel_source');
        Schema::rename('channel_entries', 'channel_entry');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('channel_source', 'channel_sources');
        Schema::rename('channel_entry', 'channel_entries');
    }
}
