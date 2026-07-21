<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record when a guest actually left, which is not always when they said they
 * would. Without it an early departure is indistinguishable from a stay that
 * ran its full course, and neither the folio nor the room history says so.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('checked_out_at')->nullable()->after('check_out_at');
        });

        // Stays already closed keep their booked departure as the actual one;
        // it is the only evidence we have for them.
        DB::table('bookings')
            ->where('status', 'checked_out')
            ->whereNull('checked_out_at')
            ->update(['checked_out_at' => DB::raw('check_out_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checked_out_at');
        });
    }
};
