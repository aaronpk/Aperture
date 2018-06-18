<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Entry;

class MediaEntryJoin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entry_media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('entry_id');
            $table->bigInteger('media_id');
            $table->index(['entry_id', 'media_id']);
        });
        Schema::table('media', function (Blueprint $table) {
          $table->dropColumn('entry_id');
          $table->index('filename');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('entry_media');
        Schema::table('media', function (Blueprint $table) {
            $table->integer('entry_id')->default(0);
            $table->dropIndex('media_filename');
        });
    }
}
