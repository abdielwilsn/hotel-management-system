<?php

namespace Database\Factories;

use App\Models\PosMenuItem;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosOrderItem>
 */
class PosOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 100, 3000);

        return [
            'pos_order_id' => PosOrder::factory(),
            'pos_menu_item_id' => PosMenuItem::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => round($unitPrice * $quantity, 2),
            'notes' => null,
        ];
    }
}
