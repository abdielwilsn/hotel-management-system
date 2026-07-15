<?php

namespace Database\Factories;

use App\Models\PosCategory;
use App\Models\PosOutlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosCategory>
 */
class PosCategoryFactory extends Factory
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
            'name' => fake()->randomElement(['Beers', 'Spirits', 'Cocktails', 'Starters', 'Mains', 'Desserts', 'Soft Drinks']).' '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
