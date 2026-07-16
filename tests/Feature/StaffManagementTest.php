<?php

use App\Models\Department;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('managers can view the staff index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $response = $this->actingAs($user)->get("/{$team->slug}/staff");

    $response->assertStatus(200);
});

test('receptionists cannot view the staff index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)->get("/{$team->slug}/staff")->assertForbidden();
});

test('admins can create staff', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/staff", [
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '1234567890',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response->assertRedirect("/{$team->slug}/staff");
    $this->assertDatabaseHas('staff', [
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'team_id' => $team->id,
    ]);
});

test('admins can update staff', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);
    $staff = Staff::factory()->create(['team_id' => $team->id, 'department_id' => $department->id]);

    $response = $this->actingAs($user)->patch("/{$team->slug}/staff/{$staff->id}", [
        'full_name' => 'Jane Doe',
        'email' => $staff->email,
        'role' => 'housekeeping',
        'department_id' => $department->id,
        'employment_date' => $staff->employment_date->toDateString(),
        'status' => 'active',
    ]);

    $response->assertRedirect("/{$team->slug}/staff");
    $this->assertDatabaseHas('staff', [
        'id' => $staff->id,
        'full_name' => 'Jane Doe',
        'role' => 'housekeeping',
    ]);
});

test('admins can delete staff', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);
    $staff = Staff::factory()->create(['team_id' => $team->id, 'department_id' => $department->id]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/staff/{$staff->id}");

    $response->assertRedirect("/{$team->slug}/staff");
    $this->assertModelMissing($staff);
});

test('members cannot create staff', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $department = Department::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/staff", [
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response->assertStatus(403);
});

test('staff department must belong to the same team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/staff", [
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors('department_id');
});

test('staff email must be unique per team', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);
    $existingStaff = Staff::factory()->create(['team_id' => $team->id, 'department_id' => $department->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/staff", [
        'full_name' => 'Jane Doe',
        'email' => $existingStaff->email,
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors('email');
});

test('creating staff creates a user account and adds to team', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/staff", [
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '1234567890',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response->assertRedirect("/{$team->slug}/staff");

    // Verify staff was created
    $this->assertDatabaseHas('staff', [
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'team_id' => $team->id,
    ]);

    // Verify user was created with the staff email
    $createdUser = User::where('email', 'john@example.com')->first();
    $this->assertNotNull($createdUser);
    $this->assertEquals('John Doe', $createdUser->name);

    // Verify user was added to the team with member role
    $this->assertTrue($createdUser->teams()->where('team_id', $team->id)->exists());
});
