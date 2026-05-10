<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Staff;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
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
            'department_id' => Department::factory(),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'role' => $this->faker->randomElement(['receptionist', 'housekeeping', 'accountant', 'manager', 'admin']),
            'employment_date' => $this->faker->dateTimeBetween('-5 years'),
            'salary' => $this->faker->optional()->numberBetween(30000, 100000),
            'emergency_contact_name' => $this->faker->optional()->name(),
            'emergency_contact_phone' => $this->faker->optional()->phoneNumber(),
            'profile_image_path' => null,
            'status' => 'active',
        ];
    }
}
