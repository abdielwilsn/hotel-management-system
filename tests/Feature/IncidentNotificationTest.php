<?php

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Department;
use App\Models\Incident;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Incidents\IncidentReported;
use App\Notifications\Incidents\IncidentResolved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function staffMemberOf(Team $team, Department $department, string $role = 'member', string $dataScope = 'departments'): User
{
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => $role, 'data_scope' => $dataScope]);
    $user->departments()->attach($department, ['team_id' => $team->id]);

    return $user;
}

test('filing an incident notifies resolvers in the department it was filed against, not an unrelated one', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);
    // Operations resolves incidents by default (confirmed in IncidentReportingTest);
    // Main Bar does not.
    $operations = Department::factory()->for($team)->create(['name' => 'Operations']);
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);

    $cleaner = staffMemberOf($team, $housekeeping);
    // Scoped to see every department's records, like the manager overseeing
    // the whole property rather than one department's own staff — otherwise
    // "can resolve incidents" and "can see this specific department" are two
    // separate gates and a same-department incident wouldn't even qualify.
    $opsStaffer = staffMemberOf($team, $operations, dataScope: 'all');
    $barStaff = staffMemberOf($team, $bar, dataScope: 'all');

    $this->actingAs($cleaner)
        ->post("/{$team->slug}/incidents", [
            'department_id' => $housekeeping->id,
            'title' => 'Broken shower in 204',
            'description' => 'Hot tap will not shut off.',
            'category' => 'maintenance',
            'severity' => IncidentSeverity::High->value,
            'occurred_at' => now()->subHour()->toDateTimeString(),
        ])
        ->assertRedirect("/{$team->slug}/incidents");

    Notification::assertSentTo($opsStaffer, IncidentReported::class);
    Notification::assertNotSentTo($barStaff, IncidentReported::class);
    Notification::assertNotSentTo($cleaner, IncidentReported::class);
});

test('resolving an incident notifies the reporter, reopening it does not', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $operations = Department::factory()->for($team)->create(['name' => 'Operations']);
    $reporter = staffMemberOf($team, $operations);
    $resolver = staffMemberOf($team, $operations, 'admin');

    $incident = Incident::query()->create([
        'team_id' => $team->id,
        'department_id' => $operations->id,
        'title' => 'AC unit rattling',
        'description' => 'Needs a technician.',
        'category' => 'maintenance',
        'severity' => IncidentSeverity::Medium,
        'status' => IncidentStatus::Open,
        'occurred_at' => now(),
        'reported_by_user_id' => $reporter->id,
    ]);

    $this->actingAs($resolver)
        ->patch("/{$team->slug}/incidents/{$incident->id}", [
            'status' => 'investigating',
        ])
        ->assertRedirect("/{$team->slug}/incidents");

    Notification::assertNotSentTo($reporter, IncidentResolved::class);

    $this->actingAs($resolver)
        ->patch("/{$team->slug}/incidents/{$incident->id}", [
            'status' => 'resolved',
            'resolution_notes' => 'Technician fixed the mount.',
        ])
        ->assertRedirect("/{$team->slug}/incidents");

    Notification::assertSentTo($reporter, IncidentResolved::class);
});
