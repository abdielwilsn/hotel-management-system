<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
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
            'title' => fake()->randomElement([
                'Laundry supplies',
                'Air conditioning maintenance',
                'Online ads campaign',
                'Housekeeping equipment',
                'Utility bill',
            ]),
            'category' => fake()->randomElement(['utilities', 'maintenance', 'supplies', 'payroll', 'marketing', 'other']),
            'amount' => fake()->randomFloat(2, 20, 2500),
            'incurred_date' => fake()->dateTimeBetween('-45 days', 'now')->format('Y-m-d'),
            'vendor' => fake()->boolean(70) ? fake()->company() : null,
            'status' => fake()->randomElement(['pending', 'paid', 'cancelled']),
            'description' => fake()->boolean(45) ? fake()->sentence() : null,
        ];
    }
}
