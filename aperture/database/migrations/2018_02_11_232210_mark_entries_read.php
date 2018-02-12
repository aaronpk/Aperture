<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MarkEntriesRead extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_entry', function (Blueprint $table) {
            $table->tinyinteger('seen')->default(0);
            $table->index(['channel_id', 'created_at', 'seen']);
        });        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_entry', function (Blueprint $table) {
            $table->dropColumn('seen');
        });
    }
}
