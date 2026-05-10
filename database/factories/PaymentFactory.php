<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 20, 1500);

        return [
            'team_id' => null,
            'invoice_id' => null,
            'payment_number' => 'PAY-'.strtoupper(fake()->bothify('##??##')),
            'payment_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'amount' => $amount,
            'method' => fake()->randomElement(['cash', 'card', 'bank_transfer', 'online', 'other']),
            'status' => fake()->randomElement(['pending', 'completed', 'failed']),
            'reference' => fake()->boolean(60) ? strtoupper(fake()->bothify('REF-####')) : null,
            'notes' => fake()->boolean(35) ? fake()->sentence() : null,
        ];
    }
}
