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
        Schema::table('staff', function (Blueprint $table) {
            $table->enum('role', [
                'receptionist',
                'housekeeping',
                'accountant',
                'manager',
                'admin',
                'bartender',
                'waiter',
            ])->default('receptionist')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->enum('role', [
                'receptionist',
                'housekeeping',
                'accountant',
                'manager',
                'admin',
            ])->default('receptionist')->change();
        });
    }
};
