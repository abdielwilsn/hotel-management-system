<?php

namespace Database\Factories;

use App\Models\PosOutlet;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosOutlet>
 */
class PosOutletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->randomElement(['Main Bar', 'Pool Bar', 'Restaurant', 'Rooftop Grill']).' '.fake()->unique()->numberBetween(1, 9999),
            'type' => fake()->randomElement(['bar', 'restaurant']),
            'status' => 'active',
        ];
    }

    public function bar(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'bar']);
    }

    public function restaurant(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'restaurant']);
    }
}
