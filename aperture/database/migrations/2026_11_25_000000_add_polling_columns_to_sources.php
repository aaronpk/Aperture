<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\PollingSchedule;

return new class extends Migration
{
    /**
     * Aperture polls feeds itself instead of relying on Watchtower. These columns hold
     * each source's polling state.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dateTime('next_check_at')->nullable()->index();
            $table->dateTime('last_checked_at')->nullable();
            $table->dateTime('fetch_queued_at')->nullable();
            $table->unsignedInteger('check_interval')->default(60);
            $table->float('entries_per_day')->default(0);
            $table->dateTime('last_new_entry_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->integer('last_http_status')->nullable();
            $table->string('http_etag', 255)->default('');
            $table->string('http_last_modified', 100)->default('');
            $table->string('content_hash', 64)->default('');
        });

        // Work out a starting tier for every existing source from its entry history,
        // and spread the first checks out so they don't all happen at once
        PollingSchedule::backfill(now());
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn([
                'next_check_at', 'last_checked_at', 'fetch_queued_at', 'check_interval',
                'entries_per_day', 'last_new_entry_at', 'consecutive_failures',
                'last_http_status', 'http_etag', 'http_last_modified', 'content_hash',
            ]);
        });
    }
};
