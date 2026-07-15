<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingDiscount;
use App\Models\Room;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDiscount>
 */
class BookingDiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => function (): int {
                $team = Team::factory()->create();
                $room = Room::factory()->create(['team_id' => $team->id]);

                return Booking::factory()->create(['team_id' => $team->id, 'room_id' => $room->id])->id;
            },
            'team_id' => fn (array $attributes) => Booking::find($attributes['booking_id'])?->team_id,
            'type' => fake()->randomElement(['percentage', 'fixed']),
            'value' => fake()->randomElement([5, 10, 15, 1000, 2500]),
            'amount' => 0,
            'reason' => fake()->boolean() ? fake()->sentence(4) : null,
            'status' => 'pending',
            'requested_by_user_id' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function percentage(float $value = 10): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'percentage', 'value' => $value]);
    }

    public function fixed(float $value = 2000): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'fixed', 'value' => $value]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }
}
