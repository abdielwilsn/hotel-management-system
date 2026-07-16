<?php

use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;

test('managers can view the departments index page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('departments.index', ['current_team' => $team]));

    $response->assertOk();
});

test('receptionists cannot view the departments index page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($user)
        ->get(route('departments.index', ['current_team' => $team]))
        ->assertForbidden();
});

test('admins can create departments', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $response = $this
        ->actingAs($admin)
        ->post(route('departments.store', ['current_team' => $team]), [
            'name' => 'Housekeeping',
            'description' => 'Room turnover and cleaning operations',
            'status' => 'active',
            'manager_id' => $admin->id,
        ]);

    $response->assertRedirect(route('departments.index', ['current_team' => $team]));

    $this->assertDatabaseHas('departments', [
        'team_id' => $team->id,
        'name' => 'Housekeeping',
        'status' => 'active',
        'manager_id' => $admin->id,
    ]);
});

test('admins can update departments', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $department = Department::factory()->create([
        'team_id' => $team->id,
        'name' => 'Reception',
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('departments.update', [
            'current_team' => $team,
            'department' => $department,
        ]), [
            'name' => 'Front Desk',
            'description' => 'Guest check-in and check-out operations',
            'status' => 'inactive',
            'manager_id' => null,
        ]);

    $response->assertRedirect(route('departments.edit', [
        'current_team' => $team,
        'department' => $department,
    ]));

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'Front Desk',
        'status' => 'inactive',
    ]);
});

test('admins can delete departments', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $department = Department::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('departments.destroy', [
            'current_team' => $team,
            'department' => $department,
        ]));

    $response->assertRedirect(route('departments.index', ['current_team' => $team]));

    $this->assertDatabaseMissing('departments', [
        'id' => $department->id,
    ]);
});

test('members cannot create departments', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->post(route('departments.store', ['current_team' => $team]), [
            'name' => 'Accounting',
            'status' => 'active',
        ]);

    $response->assertForbidden();
});

test('department manager must belong to the same team', function () {
    $admin = User::factory()->create();
    $outsider = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $response = $this
        ->actingAs($admin)
        ->post(route('departments.store', ['current_team' => $team]), [
            'name' => 'Security',
            'status' => 'active',
            'manager_id' => $outsider->id,
        ]);

    $response->assertSessionHasErrors('manager_id');
});
