<?php

namespace Database\Factories;

use App\Models\PosOrder;
use App\Models\PosOutlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosOrder>
 */
class PosOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 200, 8000);

        return [
            'pos_outlet_id' => PosOutlet::factory(),
            'team_id' => fn (array $attributes) => PosOutlet::find($attributes['pos_outlet_id'])?->team_id,
            'order_number' => strtoupper(fake()->bothify('ORD-#####')),
            'status' => 'paid',
            'charge_type' => 'walk_in',
            'booking_id' => null,
            'room_number' => null,
            'guest_name' => null,
            'payment_mode' => fake()->randomElement(['cash', 'card', 'transfer']),
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'served_by' => fake()->name(),
            'business_date' => now()->toDateString(),
            'opened_at' => now(),
            'paid_at' => now(),
        ];
    }
}
