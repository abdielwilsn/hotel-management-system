<?php

namespace Database\Factories;

use App\Models\PosMenuItem;
use App\Models\PosStockRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosStockRecord>
 */
class PosStockRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $opening = fake()->numberBetween(0, 100);
        $new = fake()->numberBetween(0, 50);

        return [
            'pos_menu_item_id' => PosMenuItem::factory()->tracksStock(),
            'pos_outlet_id' => fn (array $attributes) => PosMenuItem::find($attributes['pos_menu_item_id'])?->pos_outlet_id,
            'team_id' => fn (array $attributes) => PosMenuItem::find($attributes['pos_menu_item_id'])?->team_id,
            'business_date' => now()->toDateString(),
            'opening_stock' => $opening,
            'new_stock' => $new,
            'total_stock' => $opening + $new,
            'sales_qty' => 0,
            'closing_stock' => $opening + $new,
            'damaged' => 0,
            'shortage' => 0,
            'excess' => 0,
            'sales_value' => 0,
            'closing_value' => 0,
            'recorded_by' => fake()->name(),
            'is_closed' => false,
        ];
    }
}
