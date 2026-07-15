<?php

namespace Database\Factories;

use App\Models\PosMenuItem;
use App\Models\PosOutlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosMenuItem>
 */
class PosMenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_outlet_id' => PosOutlet::factory(),
            'team_id' => fn (array $attributes) => PosOutlet::find($attributes['pos_outlet_id'])?->team_id,
            'pos_category_id' => null,
            'name' => ucfirst(fake()->words(2, true)).' '.fake()->unique()->numberBetween(1, 99999),
            'price' => fake()->randomFloat(2, 100, 5000),
            'unit' => fake()->randomElement(['piece', 'bottle', 'glass', 'plate']),
            'track_stock' => fake()->boolean(),
            'stock_quantity' => fake()->numberBetween(50, 500),
            'is_active' => true,
        ];
    }

    public function tracksStock(): static
    {
        return $this->state(fn (array $attributes) => ['track_stock' => true]);
    }
}
