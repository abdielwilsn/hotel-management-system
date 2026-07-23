<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // When this room was last alerted as stuck in maintenance/cleaning.
            // Re-arms whenever it's older than the room's own updated_at, so a
            // status change (and later re-staleness) can alert again.
            $table->timestamp('status_alerted_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('status_alerted_at');
        });
    }
};
