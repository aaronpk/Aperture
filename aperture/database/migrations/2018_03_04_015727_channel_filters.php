<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChannelFilters extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('include_only', 20)->default('');
            $table->text('include_keywords')->nullable();
            $table->text('exclude_types')->nullable();
            $table->text('exclude_keywords')->nullable();
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
            $table->dropColumn('include_only');
            $table->dropColumn('include_keywords');
            $table->dropColumn('exclude_types');
            $table->dropColumn('exclude_keywords');
        });
    }
}
