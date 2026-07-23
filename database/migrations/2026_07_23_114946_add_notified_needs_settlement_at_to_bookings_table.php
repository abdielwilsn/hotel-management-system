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
        Schema::table('bookings', function (Blueprint $table) {
            // Set the first time front desk is alerted that this booking needs
            // settling (departed-but-unpaid, or unsettled room charges at
            // checkout) — so the hourly/periodic sweeps don't re-notify for
            // the same outstanding balance every time they run.
            $table->timestamp('notified_needs_settlement_at')->nullable()->after('checked_out_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('notified_needs_settlement_at');
        });
    }
};
