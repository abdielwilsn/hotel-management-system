<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give bookings a time of day.
 *
 * Nights were previously whole-day arithmetic, which cannot tell an 08:00 early
 * arrival from a 14:00 one, nor either from a 05:00 walk-in. The date columns
 * stay as they are: availability, the calendar and the dashboard all query them
 * by date, and they are now kept in step with the timestamps.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('check_in_at')->nullable()->after('check_out_date');
            $table->dateTime('check_out_at')->nullable()->after('check_in_at');

            // What the guest is billed for, and the policy sentence explaining it.
            $table->unsignedSmallInteger('chargeable_nights')->nullable()->after('check_out_at');
            $table->string('nights_basis')->nullable()->after('chargeable_nights');
        });

        // Existing bookings keep their dates and gain the standard house times.
        DB::table('bookings')->orderBy('id')->chunkById(500, function ($bookings) {
            foreach ($bookings as $booking) {
                $nights = max(1, (int) round(
                    (strtotime((string) $booking->check_out_date) - strtotime((string) $booking->check_in_date)) / 86400
                ));

                DB::table('bookings')->where('id', $booking->id)->update([
                    'check_in_at' => date('Y-m-d', strtotime((string) $booking->check_in_date)).' 14:00:00',
                    'check_out_at' => date('Y-m-d', strtotime((string) $booking->check_out_date)).' 12:00:00',
                    'chargeable_nights' => $nights,
                    'nights_basis' => 'Migrated from the original date-only booking.',
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['check_in_at', 'check_out_at', 'chargeable_nights', 'nights_basis']);
        });
    }
};
