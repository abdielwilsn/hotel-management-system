<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->unsignedSmallInteger('floor');
            $table->enum('room_type', ['single', 'double', 'suite', 'deluxe', 'penthouse'])->default('double');
            $table->unsignedSmallInteger('capacity');
            $table->decimal('price_per_night', 8, 2);
            $table->enum('status', ['available', 'occupied', 'maintenance', 'cleaning'])->default('available');
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'room_number']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'room_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
