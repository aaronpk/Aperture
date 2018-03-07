<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DemoMode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('channels', function (Blueprint $table) {
        $table->boolean('hide_in_demo_mode')->default(false);
      });
      Schema::table('users', function (Blueprint $table) {
        $table->boolean('demo_mode_enabled')->default(false);
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('channels', function (Blueprint $table) {
        $table->dropColumn('hide_in_demo_mode');
      });
      Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('demo_mode_enabled');
      });
    }
}
