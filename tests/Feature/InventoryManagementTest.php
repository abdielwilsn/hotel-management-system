<?php

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins can create inventory items', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Bar',
        'type' => 'BAR',
    ]);

    $this->actingAs($user)
        ->post("/{$team->slug}/inventory", [
            'inventory_category_id' => $category->id,
            'name' => 'New Premium Drink',
            'unit_price' => 1450,
            'unit' => 'bottle',
            'is_active' => true,
        ])
        ->assertRedirect("/{$team->slug}/inventory");

    $this->assertDatabaseHas('inventory_items', [
        'team_id' => $team->id,
        'inventory_category_id' => $category->id,
        'name' => 'New Premium Drink',
    ]);
});

test('admins can update inventory items', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Kitchen',
        'type' => 'KITCHEN',
    ]);

    $item = InventoryItem::query()->create([
        'team_id' => $team->id,
        'inventory_category_id' => $category->id,
        'name' => 'Noodles',
        'unit_price' => 450,
        'unit' => 'pack',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch("/{$team->slug}/inventory/{$item->id}", [
            'inventory_category_id' => $category->id,
            'name' => 'Noodles Super Pack',
            'unit_price' => 550,
            'unit' => 'pack',
            'is_active' => false,
        ])
        ->assertRedirect("/{$team->slug}/inventory");

    $this->assertDatabaseHas('inventory_items', [
        'id' => $item->id,
        'name' => 'Noodles Super Pack',
        'unit_price' => 550,
        'is_active' => 0,
    ]);
});

test('admins can delete inventory items', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Store',
        'type' => 'STORE',
    ]);

    $item = InventoryItem::query()->create([
        'team_id' => $team->id,
        'inventory_category_id' => $category->id,
        'name' => 'Detergent',
        'unit_price' => 1200,
        'unit' => 'pack',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->delete("/{$team->slug}/inventory/{$item->id}")
        ->assertRedirect("/{$team->slug}/inventory");

    $this->assertModelMissing($item);
});

test('members cannot create inventory items', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Mini-Shop',
        'type' => 'MINI_SHOP',
    ]);

    $this->actingAs($user)
        ->post("/{$team->slug}/inventory", [
            'inventory_category_id' => $category->id,
            'name' => 'Comb',
            'unit_price' => 300,
            'unit' => 'piece',
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('category must belong to current team when creating inventory item', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $otherTeamCategory = InventoryCategory::query()->create([
        'team_id' => $otherTeam->id,
        'name' => 'External',
        'type' => 'STORE',
    ]);

    $this->actingAs($user)
        ->post("/{$team->slug}/inventory", [
            'inventory_category_id' => $otherTeamCategory->id,
            'name' => 'Invalid Item',
            'unit_price' => 50,
            'unit' => 'piece',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('inventory_category_id');
});
