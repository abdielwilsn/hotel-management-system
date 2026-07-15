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
        Schema::table('pos_menu_items', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->after('track_stock');
        });

        Schema::create('pos_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // received | sold | damaged | adjustment | opening
            $table->integer('quantity'); // signed: +in, -out
            $table->integer('balance_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->string('reference')->nullable();
            $table->string('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->date('business_date');
            $table->timestamps();

            $table->index(['team_id', 'business_date']);
            $table->index(['pos_menu_item_id', 'business_date']);
            $table->index(['pos_outlet_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_stock_movements');

        Schema::table('pos_menu_items', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }
};
