<?php

use App\Support\StayPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clock each hotel runs on. Nights are billed from one checkout time to the
 * next, so these three times decide what a stay costs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('check_in_time', 5)->default(StayPolicy::DEFAULT_CHECK_IN_TIME)->after('locale');
            $table->string('check_out_time', 5)->default(StayPolicy::DEFAULT_CHECK_OUT_TIME)->after('check_in_time');
            $table->string('early_check_in_from', 5)->default(StayPolicy::DEFAULT_EARLY_CHECK_IN_FROM)->after('check_out_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['check_in_time', 'check_out_time', 'early_check_in_from']);
        });
    }
};
