<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\User;

class ChannelOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->integer('sort')->default(0);
        });

        $users = User::all();
        foreach($users as $user) {
            $channels = $user->channels()
              ->orderByDesc(DB::raw('uid = "default"'))
              ->orderByDesc(DB::raw('uid = "notifications"'))
              ->orderBy('name')
              ->get();
            foreach($channels as $i=>$channel) {
                $channel->sort = $i;
                $channel->save();
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
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
}
