<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class LongUrlProperties extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('url', 2048)->change();
        });
        Schema::table('entries', function (Blueprint $table) {
            $table->string('unique', 2048)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('url', 512)->change();
        });
        Schema::table('entries', function (Blueprint $table) {
            $table->string('unique', 512)->change();
        });
    }
}
