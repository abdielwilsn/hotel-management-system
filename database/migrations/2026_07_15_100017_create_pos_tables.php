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
        Schema::create('pos_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('bar');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('pos_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['pos_outlet_id', 'name']);
            $table->index('team_id');
        });

        Schema::create('pos_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('unit')->default('piece');
            $table->boolean('track_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pos_outlet_id', 'name']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('pos_stock_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_menu_item_id')->constrained()->cascadeOnDelete();
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

            $table->unique(['pos_menu_item_id', 'business_date']);
            $table->index(['team_id', 'business_date']);
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_outlet_id')->constrained()->cascadeOnDelete();
            $table->string('order_number');
            $table->string('status')->default('open');
            $table->string('charge_type')->default('walk_in');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('room_number')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('payment_mode')->default('cash');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('served_by')->nullable();
            $table->date('business_date');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'order_number']);
            $table->index(['team_id', 'business_date']);
            $table->index(['pos_outlet_id', 'business_date']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('pos_order_id');
        });

        Schema::create('pos_outlet_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pos_outlet_id', 'user_id']);
            $table->index(['team_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_outlet_user');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_stock_records');
        Schema::dropIfExists('pos_menu_items');
        Schema::dropIfExists('pos_categories');
        Schema::dropIfExists('pos_outlets');
    }
};
