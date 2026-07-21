<?php

use App\Enums\Ability;
use App\Enums\DataScope;
use App\Models\Department;
use App\Models\PosOutlet;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Attach a user to a team with the given base membership role.
 */
function joinTeam(Team $team, User $user, string $role = 'member'): void
{
    $user->teams()->attach($team, ['role' => $role]);
}

test('an owner keeps every ability whatever their departments say', function () {
    $team = Team::factory()->create();
    $locked = Department::factory()->for($team)->create([
        'name' => 'Housekeeping',
        'abilities' => [],
    ]);

    $owner = User::factory()->create();
    joinTeam($team, $owner, 'owner');
    $owner->departments()->attach($locked, ['team_id' => $team->id]);

    expect($owner->hasAbility(Ability::ManagePermissions, $team))->toBeTrue();
    expect($owner->hasAbility(Ability::ManageExpenses, $team))->toBeTrue();
});

test('a manager can move a member between departments', function () {
    $team = Team::factory()->create();
    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $finance = Department::factory()->for($team)->create(['name' => 'Finance']);

    $manager = User::factory()->create();
    $member = User::factory()->create();
    joinTeam($team, $manager, 'admin');
    joinTeam($team, $member, 'member');
    $member->departments()->attach($frontDesk, ['team_id' => $team->id]);

    $this->actingAs($manager)
        ->patch("/settings/teams/{$team->slug}/members/{$member->id}/access", [
            'data_scope' => DataScope::Departments->value,
            'department_ids' => [$finance->id],
        ])
        ->assertRedirect("/settings/teams/{$team->slug}");

    $member->refresh();

    // Moving department moves their permissions with them.
    expect($member->hasAbility(Ability::ManageExpenses, $team))->toBeTrue();
    expect($member->hasAbility(Ability::ManageBookings, $team))->toBeFalse();
});

test('a member keeps their department permissions when allowed to see everything', function () {
    $team = Team::factory()->create();
    $finance = Department::factory()->for($team)->create(['name' => 'Finance']);

    $manager = User::factory()->create();
    $member = User::factory()->create();
    joinTeam($team, $manager, 'admin');
    joinTeam($team, $member, 'member');

    $this->actingAs($manager)
        ->patch("/settings/teams/{$team->slug}/members/{$member->id}/access", [
            'data_scope' => DataScope::All->value,
            'department_ids' => [$finance->id],
        ]);

    $member->refresh();

    // Working in Finance is what grants the abilities; the wider data scope
    // must not quietly strip them.
    expect($member->hasAbility(Ability::ManageExpenses, $team))->toBeTrue();
    expect($member->visibleDepartmentIds($team))->toBeNull();
});

test('a department from another team cannot be assigned to a member', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $foreign = Department::factory()->for($otherTeam)->create();

    $manager = User::factory()->create();
    $member = User::factory()->create();
    joinTeam($team, $manager, 'admin');
    joinTeam($team, $member, 'member');

    $this->actingAs($manager)
        ->patch("/settings/teams/{$team->slug}/members/{$member->id}/access", [
            'data_scope' => DataScope::Departments->value,
            'department_ids' => [$foreign->id],
        ])
        ->assertSessionHasErrors('department_ids.0');
});

test('a member without an assigned role falls back to their base role defaults', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    joinTeam($team, $user, 'member');

    expect($user->hasAbility(Ability::ViewBookings, $team))->toBeTrue();
    expect($user->hasAbility(Ability::ManageExpenses, $team))->toBeFalse();
});

test('a department scoped manager only sees staff from their own departments', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create();
    $kitchen = Department::factory()->for($team)->create();

    $ours = Staff::factory()->for($team)->for($housekeeping)->create(['full_name' => 'Ada Housekeeper']);
    Staff::factory()->for($team)->for($kitchen)->create(['full_name' => 'Bela Chef']);

    $user = User::factory()->create();
    joinTeam($team, $user, 'admin');

    $team->memberships()->where('user_id', $user->id)
        ->update(['data_scope' => DataScope::Departments->value]);
    $user->departments()->attach($housekeeping, ['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/{$team->slug}/staff")
        ->assertInertia(fn ($page) => $page
            ->has('staff', 1)
            ->where('staff.0.full_name', $ours->full_name)
        );
});

test('a department scoped manager cannot edit staff outside their departments', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create();
    $kitchen = Department::factory()->for($team)->create();

    $outsider = Staff::factory()->for($team)->for($kitchen)->create();

    $user = User::factory()->create();
    joinTeam($team, $user, 'admin');

    $team->memberships()->where('user_id', $user->id)
        ->update(['data_scope' => DataScope::Departments->value]);
    $user->departments()->attach($housekeeping, ['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/{$team->slug}/staff/{$outsider->id}/edit")
        ->assertForbidden();
});

test('the team owner access cannot be changed', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    joinTeam($team, $owner, 'owner');
    joinTeam($team, $manager, 'admin');

    $this->actingAs($manager)
        ->patch("/settings/teams/{$team->slug}/members/{$owner->id}/access", [
            'data_scope' => DataScope::Departments->value,
            'department_ids' => [],
        ])
        ->assertForbidden();
});

test('a department grants the abilities of its preset', function () {
    $team = Team::factory()->create();
    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);

    expect($frontDesk->abilities)->toContain(Ability::ManageBookings->value);
    expect($frontDesk->abilities)->not->toContain(Ability::OperatePos->value);
    expect($frontDesk->abilities)->not->toContain(Ability::ManageExpenses->value);
});

test('a department with no preset starts with nothing but hotel access', function () {
    $team = Team::factory()->create();
    $department = Department::factory()->for($team)->create(['name' => 'Laundry']);

    expect($department->abilities)->toBe(Department::DEFAULT_ABILITIES);
});

test('a member draws their abilities from the department they work in', function () {
    $team = Team::factory()->create();
    $finance = Department::factory()->for($team)->create(['name' => 'Finance']);

    $user = User::factory()->create();
    joinTeam($team, $user, 'member');
    $user->departments()->attach($finance, ['team_id' => $team->id]);

    // Granted by Finance, and not by the generic member role.
    expect($user->hasAbility(Ability::ManageExpenses, $team))->toBeTrue();
    expect($user->hasAbility(Ability::OperatePos, $team))->toBeFalse();

    $this->actingAs($user)->get("/{$team->slug}/expenses")->assertOk();
});

test('editing a department changes what its people can do', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);

    $user = User::factory()->create();
    joinTeam($team, $user, 'member');
    $user->departments()->attach($housekeeping, ['team_id' => $team->id]);

    $this->actingAs($user)->get("/{$team->slug}/expenses")->assertForbidden();

    $housekeeping->update([
        'abilities' => [...$housekeeping->abilities, Ability::ViewExpenses->value],
    ]);

    $this->actingAs($user)->get("/{$team->slug}/expenses")->assertOk();
});

test('a member in two departments holds the abilities of both', function () {
    $team = Team::factory()->create();
    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $finance = Department::factory()->for($team)->create(['name' => 'Finance']);

    $user = User::factory()->create();
    joinTeam($team, $user, 'member');
    $user->departments()->attach($frontDesk, ['team_id' => $team->id]);
    $user->departments()->attach($finance, ['team_id' => $team->id]);

    expect($user->hasAbility(Ability::ManageBookings, $team))->toBeTrue();
    expect($user->hasAbility(Ability::ManageExpenses, $team))->toBeTrue();
});

test('a member in no department still falls back to their base role', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    joinTeam($team, $user, 'member');

    expect($user->hasAbility(Ability::ViewBookings, $team))->toBeTrue();
});

test('admins are not limited by department permissions', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);

    $admin = User::factory()->create();
    joinTeam($team, $admin, 'admin');
    $admin->departments()->attach($housekeeping, ['team_id' => $team->id]);

    expect($admin->hasAbility(Ability::ManageExpenses, $team))->toBeTrue();
    expect($admin->hasAbility(Ability::ManagePermissions, $team))->toBeTrue();
});

test('a manager can change what a department is allowed to do', function () {
    $team = Team::factory()->create();
    $housekeeping = Department::factory()->for($team)->create(['name' => 'Housekeeping']);

    $manager = User::factory()->create();
    joinTeam($team, $manager, 'admin');

    $this->actingAs($manager)
        ->patch("/{$team->slug}/departments/{$housekeeping->id}", [
            'name' => 'Housekeeping',
            'status' => 'active',
            'abilities' => [Ability::AccessHotel->value, Ability::ViewInventory->value],
        ])
        ->assertRedirect();

    expect($housekeeping->fresh()->abilities)
        ->toBe([Ability::AccessHotel->value, Ability::ViewInventory->value]);
});

test('someone who cannot manage permissions cannot grant them via a department', function () {
    $team = Team::factory()->create();

    // A department that can reorganise departments but not manage permissions.
    $operations = Department::factory()->for($team)->create([
        'name' => 'Operations',
        'abilities' => [
            Ability::AccessHotel->value,
            Ability::ViewDepartments->value,
            Ability::ManageDepartments->value,
        ],
    ]);

    $supervisor = User::factory()->create();
    joinTeam($team, $supervisor, 'member');
    $supervisor->departments()->attach($operations, ['team_id' => $team->id]);

    expect($supervisor->hasAbility(Ability::ManagePermissions, $team))->toBeFalse();

    // They may rename the department, but the abilities must be ignored.
    $this->actingAs($supervisor)
        ->patch("/{$team->slug}/departments/{$operations->id}", [
            'name' => 'Operations Renamed',
            'status' => 'active',
            'abilities' => Ability::values(),
        ])
        ->assertRedirect();

    $operations->refresh();

    expect($operations->name)->toBe('Operations Renamed');
    expect($operations->abilities)->not->toContain(Ability::ManageExpenses->value);
    expect($supervisor->fresh()->hasAbility(Ability::ManageExpenses, $team))->toBeFalse();
});

test('front desk staff cannot work an outlet belonging to another department', function () {
    $team = Team::factory()->create();
    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);

    $outlet = PosOutlet::factory()->for($team)->for($bar)->create(['name' => 'Main Bar']);

    $receptionist = User::factory()->create();
    joinTeam($team, $receptionist, 'member');

    $team->memberships()->where('user_id', $receptionist->id)
        ->update(['data_scope' => DataScope::Departments->value]);
    $receptionist->departments()->attach($frontDesk, ['team_id' => $team->id]);

    // The Front Desk department simply does not grant the till.
    expect($receptionist->hasAbility(Ability::OperatePos, $team))->toBeFalse();
    expect($receptionist->canAccessPosOutlet($outlet))->toBeFalse();

    $this->actingAs($receptionist)
        ->get("/{$team->slug}/pos/{$outlet->id}/terminal")
        ->assertForbidden();
});

test('front desk staff cannot open the point of sale at all', function () {
    $team = Team::factory()->create();
    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);

    PosOutlet::factory()->for($team)->for($bar)->create();

    $receptionist = User::factory()->create();
    joinTeam($team, $receptionist, 'member');

    $team->memberships()->where('user_id', $receptionist->id)
        ->update(['data_scope' => DataScope::Departments->value]);
    $receptionist->departments()->attach($frontDesk, ['team_id' => $team->id]);

    $this->actingAs($receptionist)
        ->get("/{$team->slug}/pos")
        ->assertForbidden();
});

test('bar staff scoped to the bar can still work their own outlet', function () {
    $team = Team::factory()->create();
    Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);

    $outlet = PosOutlet::factory()->for($team)->for($bar)->create();

    $barman = User::factory()->create();
    joinTeam($team, $barman, 'member');

    $team->memberships()->where('user_id', $barman->id)
        ->update(['data_scope' => DataScope::Departments->value]);
    $barman->departments()->attach($bar, ['team_id' => $team->id]);

    expect($barman->canAccessPosOutlet($outlet))->toBeTrue();

    $this->actingAs($barman)
        ->get("/{$team->slug}/pos/{$outlet->id}/terminal")
        ->assertOk();
});

test('pos only staff still cannot reach the hotel modules', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    joinTeam($team, $user, 'pos');

    $this->actingAs($user)->get("/{$team->slug}/bookings")->assertForbidden();
    $this->actingAs($user)->get("/{$team->slug}/pos")->assertOk();
});
