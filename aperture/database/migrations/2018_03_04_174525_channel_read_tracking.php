<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChannelReadTracking extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::table('channels', function (Blueprint $table) {
      $table->string('read_tracking_mode', 20)->default('count');
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
      $table->dropColumn('read_tracking_mode');
    });
  }
}
