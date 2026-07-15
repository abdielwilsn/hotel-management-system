<?php

use App\Models\Department;
use App\Models\PosCategory;
use App\Models\PosMenuItem;
use App\Models\PosOutlet;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function posAdminContext(): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    return [$team, $user];
}

test('admins can create an outlet', function () {
    [$team, $user] = posAdminContext();

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/outlets", [
            'name' => 'Rooftop Bar',
            'type' => 'bar',
            'status' => 'active',
        ])
        ->assertRedirect("/{$team->slug}/pos/manage");

    $this->assertDatabaseHas('pos_outlets', [
        'team_id' => $team->id,
        'name' => 'Rooftop Bar',
        'type' => 'bar',
    ]);
});

test('admins can create categories and menu items for an outlet', function () {
    [$team, $user] = posAdminContext();
    $outlet = PosOutlet::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/categories", ['name' => 'Cocktails'])
        ->assertRedirect("/{$team->slug}/pos/{$outlet->id}/menu");

    $category = PosCategory::query()->where('name', 'Cocktails')->first();
    expect($category)->not->toBeNull();

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/items", [
            'pos_category_id' => $category->id,
            'name' => 'Mojito',
            'price' => 2200,
            'unit' => 'glass',
            'track_stock' => true,
            'is_active' => true,
        ])
        ->assertRedirect("/{$team->slug}/pos/{$outlet->id}/menu");

    $this->assertDatabaseHas('pos_menu_items', [
        'pos_outlet_id' => $outlet->id,
        'name' => 'Mojito',
        'track_stock' => true,
    ]);
});

test('creating an outlet also creates a matching department', function () {
    [$team, $user] = posAdminContext();

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/outlets", [
            'name' => 'Pool Bar',
            'type' => 'bar',
            'status' => 'active',
        ]);

    $this->assertDatabaseHas('departments', [
        'team_id' => $team->id,
        'name' => 'Pool Bar',
    ]);

    $outlet = PosOutlet::query()->where('name', 'Pool Bar')->firstOrFail();
    $department = Department::query()->where('name', 'Pool Bar')->firstOrFail();
    expect($outlet->department_id)->toBe($department->id);
});

test('assigning a pos user to an outlet registers them in the staff directory', function () {
    [$team, $user] = posAdminContext();
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id, 'name' => 'Main Bar']);

    $barman = User::factory()->create(['name' => 'Barman Joe']);
    $barman->teams()->attach($team, ['role' => 'pos']);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/outlets/{$outlet->id}/staff", ['user_id' => $barman->id])
        ->assertRedirect("/{$team->slug}/pos/manage");

    $staff = Staff::query()->where('email', $barman->email)->first();
    expect($staff)->not->toBeNull();
    expect($staff->full_name)->toBe('Barman Joe');
    expect($staff->role)->toBe('bartender');
    expect($staff->department->name)->toBe('Main Bar');
});

test('pos staff cannot create outlets', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'pos']);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/outlets", [
            'name' => 'Sneaky Bar',
            'type' => 'bar',
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('admins can assign and unassign pos staff to an outlet', function () {
    [$team, $user] = posAdminContext();
    $outlet = PosOutlet::factory()->create(['team_id' => $team->id]);

    $barman = User::factory()->create();
    $barman->teams()->attach($team, ['role' => 'pos']);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/outlets/{$outlet->id}/staff", ['user_id' => $barman->id])
        ->assertRedirect("/{$team->slug}/pos/manage");

    expect($barman->fresh()->canAccessPosOutlet($outlet))->toBeTrue();

    $this->actingAs($user)
        ->delete("/{$team->slug}/pos/outlets/{$outlet->id}/staff/{$barman->id}")
        ->assertRedirect("/{$team->slug}/pos/manage");

    expect($barman->fresh()->canAccessPosOutlet($outlet))->toBeFalse();
});

test('a menu item cannot be edited from a different outlet', function () {
    [$team, $user] = posAdminContext();
    $outlet = PosOutlet::factory()->create(['team_id' => $team->id]);
    $otherOutlet = PosOutlet::factory()->create(['team_id' => $team->id]);
    $item = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $otherOutlet->id]);

    $this->actingAs($user)
        ->get("/{$team->slug}/pos/{$outlet->id}/items/{$item->id}/edit")
        ->assertForbidden();
});
