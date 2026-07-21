<?php

use App\Enums\Ability;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Department;
use App\Models\Incident;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Somebody who works in the given department, on the given team.
 */
function staffOf(Team $team, Department $department, string $role = 'member'): User
{
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => $role, 'data_scope' => 'departments']);
    $user->departments()->attach($department, ['team_id' => $team->id]);

    return $user;
}

test('every department can report and read incidents by default', function () {
    $team = Team::factory()->create();

    foreach (['Front Desk', 'Housekeeping', 'Main Bar', 'Laundry'] as $name) {
        $department = Department::factory()->for($team)->create(['name' => $name]);

        expect($department->hasAbility(Ability::ReportIncidents))->toBeTrue();
        expect($department->hasAbility(Ability::ViewIncidents))->toBeTrue();
    }
});

test('only the departments that run the hotel can close incidents by default', function () {
    $team = Team::factory()->create();

    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);
    $operations = Department::factory()->for($team)->create(['name' => 'Operations']);

    expect($bar->hasAbility(Ability::ResolveIncidents))->toBeFalse();
    expect($operations->hasAbility(Ability::ResolveIncidents))->toBeTrue();
});

test('a staff member can file an incident against their own department', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);
    $cleaner = staffOf($team, $housekeeping);

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

    $incident = Incident::first();

    expect($incident->department_id)->toBe($housekeeping->id);
    expect($incident->reported_by_user_id)->toBe($cleaner->id);
    expect($incident->status)->toBe(IncidentStatus::Open);
});

test('a staff member cannot file an incident against a department they are not in', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);
    $finance = Department::factory()->for($team)->create(['name' => 'Finance']);
    $cleaner = staffOf($team, $housekeeping);

    $this->actingAs($cleaner)
        ->post("/{$team->slug}/incidents", [
            'department_id' => $finance->id,
            'title' => 'Not my department',
            'description' => 'Should be refused.',
            'category' => 'other',
            'severity' => IncidentSeverity::Low->value,
            'occurred_at' => now()->subHour()->toDateTimeString(),
        ])
        ->assertForbidden();

    expect(Incident::count())->toBe(0);
});

test('staff only see incidents from their own departments', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);

    Incident::factory()->for($team)->for($housekeeping)->create(['title' => 'Ours']);
    Incident::factory()->for($team)->for($bar)->create(['title' => 'Theirs']);

    $cleaner = staffOf($team, $housekeeping);

    $this->actingAs($cleaner)
        ->get("/{$team->slug}/incidents")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('incidents', 1)
            ->where('incidents.0.title', 'Ours')
        );
});

test('a manager sees every department incident', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);

    Incident::factory()->for($team)->for($housekeeping)->create();
    Incident::factory()->for($team)->for($bar)->create();

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->get("/{$team->slug}/incidents")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('incidents', 2)->where('canResolve', true));
});

test('bar staff can report incidents even though they cannot reach the hotel modules', function () {
    $team = Team::factory()->create();
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);
    $barman = staffOf($team, $bar);

    // The bar preset deliberately withholds hotel access.
    expect($barman->hasAbility(Ability::AccessHotel, $team))->toBeFalse();
    $this->actingAs($barman)->get("/{$team->slug}/bookings")->assertForbidden();

    // Incidents are still reachable, because the shift still has to be logged.
    $this->actingAs($barman)->get("/{$team->slug}/incidents")->assertOk();

    $this->actingAs($barman)
        ->post("/{$team->slug}/incidents", [
            'department_id' => $bar->id,
            'title' => 'Glass breakage behind the bar',
            'description' => 'Crate dropped during restock.',
            'category' => 'safety',
            'severity' => IncidentSeverity::Medium->value,
            'occurred_at' => now()->subMinutes(20)->toDateTimeString(),
        ])
        ->assertRedirect();

    expect(Incident::count())->toBe(1);
});

test('a reporter without the resolve ability cannot close their own incident', function () {
    $team = Team::factory()->create();
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);
    $barman = staffOf($team, $bar);

    $incident = Incident::factory()->for($team)->for($bar)->create([
        'reported_by_user_id' => $barman->id,
    ]);

    $this->actingAs($barman)
        ->patch("/{$team->slug}/incidents/{$incident->id}", [
            'status' => IncidentStatus::Resolved->value,
            'resolution_notes' => 'Sorted it myself.',
        ])
        ->assertForbidden();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Open);
});

test('a manager resolving an incident records who closed it and when', function () {
    $team = Team::factory()->create();
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);
    $incident = Incident::factory()->for($team)->for($bar)->create();

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->patch("/{$team->slug}/incidents/{$incident->id}", [
            'status' => IncidentStatus::Resolved->value,
            'resolution_notes' => 'Crate restacked, floor cleared.',
        ])
        ->assertRedirect();

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::Resolved);
    expect($incident->resolved_by_user_id)->toBe($manager->id);
    expect($incident->resolved_at)->not->toBeNull();
    expect($incident->isOpen())->toBeFalse();
});

test('reopening an incident clears the previous sign off', function () {
    $team = Team::factory()->create();
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);
    $incident = Incident::factory()->for($team)->for($bar)->resolved()->create();

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->patch("/{$team->slug}/incidents/{$incident->id}", [
            'status' => IncidentStatus::Investigating->value,
        ])
        ->assertRedirect();

    $incident->refresh();

    expect($incident->resolved_by_user_id)->toBeNull();
    expect($incident->resolved_at)->toBeNull();
    expect($incident->isOpen())->toBeTrue();
});

test('a manager cannot resolve an incident on another team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $department = Department::factory()->for($otherTeam)->create();
    $incident = Incident::factory()->for($otherTeam)->for($department)->create();

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->patch("/{$team->slug}/incidents/{$incident->id}", [
            'status' => IncidentStatus::Resolved->value,
        ])
        ->assertNotFound();
});

test('the worst incidents are listed first', function () {
    $team = Team::factory()->create();
    $department = Department::factory()->for($team)->create();

    Incident::factory()->for($team)->for($department)->severity(IncidentSeverity::Low)->create(['title' => 'Minor']);
    Incident::factory()->for($team)->for($department)->severity(IncidentSeverity::Critical)->create(['title' => 'Serious']);
    Incident::factory()->for($team)->for($department)->severity(IncidentSeverity::Medium)->create(['title' => 'Middling']);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->get("/{$team->slug}/incidents")
        ->assertInertia(fn ($page) => $page
            ->where('incidents.0.title', 'Serious')
            ->where('incidents.1.title', 'Middling')
            ->where('incidents.2.title', 'Minor')
        );
});

test('an incident cannot be reported in the future', function () {
    $team = Team::factory()->create();
    $department = Department::factory()->for($team)->create(['name' => 'Housekeeping']);
    $cleaner = staffOf($team, $department);

    $this->actingAs($cleaner)
        ->post("/{$team->slug}/incidents", [
            'department_id' => $department->id,
            'title' => 'Tomorrow problem',
            'description' => 'Has not happened.',
            'category' => 'other',
            'severity' => IncidentSeverity::Low->value,
            'occurred_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertSessionHasErrors('occurred_at');
});
