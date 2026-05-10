<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 2000);
        $tax = round($subtotal * 0.1, 2);
        $discount = fake()->boolean(25) ? fake()->randomFloat(2, 0, 120) : 0;
        $total = max($subtotal + $tax - $discount, 0);
        $paid = fake()->boolean(30) ? fake()->randomFloat(2, 0, $total) : 0;

        return [
            'team_id' => null,
            'booking_id' => null,
            'invoice_number' => 'INV-'.strtoupper(fake()->bothify('##??##')),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'issue_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'status' => fake()->randomElement(['draft', 'issued', 'partially_paid', 'paid', 'overdue']),
            'notes' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }
}
