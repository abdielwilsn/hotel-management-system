<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('-30 days', '+30 days');
        $checkOut = (clone $checkIn)->modify('+' . $this->faker->numberBetween(1, 7) . ' days');
        $nights = (new \DateTime($checkIn->format('Y-m-d')))->diff(new \DateTime($checkOut->format('Y-m-d')))->days;
        $pricePerNight = $this->faker->randomFloat(2, 80, 400);

        return [
            'team_id' => null,
            'room_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_email' => $this->faker->safeEmail(),
            'guest_phone' => $this->faker->phoneNumber(),
            'number_of_guests' => $this->faker->numberBetween(1, 4),
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'price_per_night' => $pricePerNight,
            'total_amount' => $pricePerNight * $nights,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
