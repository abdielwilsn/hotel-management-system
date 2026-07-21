<?php

use App\Models\Department;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

test('a manager can set the staff login password directly', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $admin->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);

    $this->actingAs($admin)->post("/{$team->slug}/staff", [
        'full_name' => 'Grace Ade',
        'email' => 'grace@example.com',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
        'password' => 'front-desk-2026!',
        'password_confirmation' => 'front-desk-2026!',
    ])->assertRedirect("/{$team->slug}/staff");

    $created = User::query()->where('email', 'grace@example.com')->first();

    expect($created)->not->toBeNull();
    expect(Hash::check('front-desk-2026!', $created->password))->toBeTrue();
});

test('the password must be confirmed', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $admin->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);

    $this->actingAs($admin)->post("/{$team->slug}/staff", [
        'full_name' => 'Mismatch Person',
        'email' => 'mismatch@example.com',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
        'password' => 'front-desk-2026!',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');

    $this->assertDatabaseMissing('staff', ['email' => 'mismatch@example.com']);
});

test('staff can be created without a password', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $admin->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);

    $this->actingAs($admin)->post("/{$team->slug}/staff", [
        'full_name' => 'No Password',
        'email' => 'nopassword@example.com',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
    ])->assertRedirect("/{$team->slug}/staff");

    $this->assertDatabaseHas('users', ['email' => 'nopassword@example.com']);
});

test('adding staff never overwrites an existing account password', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $admin->teams()->attach($team, ['role' => 'admin']);
    $department = Department::factory()->create(['team_id' => $team->id]);

    // Somebody who already has a login (e.g. the owner).
    $existing = User::factory()->create([
        'email' => 'owner@example.com',
        'password' => Hash::make('the-real-owner-password'),
    ]);

    $this->actingAs($admin)->post("/{$team->slug}/staff", [
        'full_name' => 'Owner Person',
        'email' => 'owner@example.com',
        'role' => 'receptionist',
        'department_id' => $department->id,
        'employment_date' => now()->toDateString(),
        'status' => 'active',
        'password' => 'attacker-chosen-password',
        'password_confirmation' => 'attacker-chosen-password',
    ])->assertRedirect("/{$team->slug}/staff");

    $existing->refresh();

    expect(Hash::check('the-real-owner-password', $existing->password))->toBeTrue();
    expect(Hash::check('attacker-chosen-password', $existing->password))->toBeFalse();
});
