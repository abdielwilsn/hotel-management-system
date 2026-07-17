<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Room types are managed per team now, so the column can no longer be a
     * fixed enum — it stores whichever room_types.slug the team defined.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type')->default('double')->change();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('room_type', ['single', 'double', 'suite', 'deluxe', 'penthouse'])
                ->default('double')
                ->change();
        });
    }
};
