<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
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
            'room_number' => $this->faker->unique()->numerify('###'),
            'floor' => $this->faker->numberBetween(1, 5),
            'room_type' => $this->faker->randomElement(['single', 'double', 'suite', 'deluxe', 'penthouse']),
            'capacity' => $this->faker->numberBetween(1, 4),
            'price_per_night' => $this->faker->randomFloat(2, 50, 500),
            'status' => $this->faker->randomElement(['available', 'occupied', 'maintenance', 'cleaning']),
            'description' => $this->faker->sentence(),
            'features' => [
                'wifi' => true,
                'tv' => true,
                'ac' => true,
                'minibar' => $this->faker->boolean(),
                'balcony' => $this->faker->boolean(),
            ],
        ];
    }
}
