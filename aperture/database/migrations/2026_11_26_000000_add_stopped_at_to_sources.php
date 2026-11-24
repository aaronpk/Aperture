<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A source that fails every fetch for 30 days stops being polled. failing_since is
     * when its current run of failures began, and stopped_at is when polling stopped.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dateTime('failing_since')->nullable();
            $table->dateTime('stopped_at')->nullable();
        });

        // Sources that are already failing start their 30 days now, since it isn't
        // recorded when their failures began
        DB::table('sources')->where('consecutive_failures', '>', 0)->update(['failing_since' => now()]);
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['failing_since', 'stopped_at']);
        });
    }
};
