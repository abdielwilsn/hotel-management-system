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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('category', ['utilities', 'maintenance', 'supplies', 'payroll', 'marketing', 'other']);
            $table->decimal('amount', 10, 2);
            $table->date('incurred_date');
            $table->string('vendor')->nullable();
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('paid');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'category']);
            $table->index(['team_id', 'incurred_date']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
