<?php

namespace Database\Factories;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Department;
use App\Models\Incident;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
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
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(IncidentCategory::cases())->value,
            'severity' => IncidentSeverity::Low->value,
            'status' => IncidentStatus::Open->value,
            'occurred_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ];
    }

    public function severity(IncidentSeverity $severity): static
    {
        return $this->state(fn () => ['severity' => $severity->value]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => IncidentStatus::Resolved->value,
            'resolved_at' => now(),
        ]);
    }
}
