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
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('source_external_id')->nullable();
            $table->string('type')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->unique(['team_id', 'source_external_id']);
            $table->index(['team_id', 'type']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_category_id')->constrained()->cascadeOnDelete();
            $table->string('source_external_id')->nullable();
            $table->string('name');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('unit')->default('piece');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'source_external_id']);
            $table->unique(['team_id', 'inventory_category_id', 'name']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('inventory_stock_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('source_external_id')->nullable();
            $table->date('business_date');
            $table->integer('opening_stock')->default(0);
            $table->integer('new_stock')->default(0);
            $table->integer('total_stock')->default(0);
            $table->integer('sales_qty')->default(0);
            $table->integer('closing_stock')->default(0);
            $table->integer('damaged')->default(0);
            $table->integer('shortage')->default(0);
            $table->integer('excess')->default(0);
            $table->decimal('sales_value', 12, 2)->default(0);
            $table->decimal('closing_value', 12, 2)->default(0);
            $table->string('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['team_id', 'source_external_id']);
            $table->unique(['inventory_item_id', 'business_date']);
            $table->index(['team_id', 'business_date']);
        });

        Schema::create('inventory_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('source_external_id')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('room_number')->nullable();
            $table->string('guest_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_mode')->default('cash');
            $table->date('business_date');
            $table->string('officer_name')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'source_external_id']);
            $table->index(['team_id', 'business_date']);
            $table->index(['team_id', 'payment_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_sales');
        Schema::dropIfExists('inventory_stock_records');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
    }
};
