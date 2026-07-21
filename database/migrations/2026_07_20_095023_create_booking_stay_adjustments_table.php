<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request to bill a stay for something other than what the policy computed.
 *
 * Both of the cases the desk runs into are the same object: rolling an early
 * arrival over to the next day without charging twice, and treating a pre-dawn
 * walk-in as a single day. Mirrors booking_discounts, which already works this
 * way, so the desk and the manager learn one flow rather than two.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_stay_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('computed_nights');
            $table->unsignedSmallInteger('requested_nights');
            $table->string('basis')->nullable();
            $table->string('reason')->nullable();

            $table->string('status')->default('pending');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['booking_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_stay_adjustments');
    }
};
