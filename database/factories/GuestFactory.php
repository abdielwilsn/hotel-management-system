<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'date_of_birth' => fake()->optional()->dateTimeBetween('-70 years', '-18 years')?->format('Y-m-d'),
            'loyalty_tier' => fake()->randomElement(['standard', 'silver', 'gold', 'platinum']),
            'loyalty_points' => fake()->numberBetween(0, 25000),
            'last_stay_date' => fake()->optional()->dateTimeBetween('-1 year', 'now')?->format('Y-m-d'),
            'preferences' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
