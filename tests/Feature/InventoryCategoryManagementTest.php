<?php

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins can create inventory categories', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->post("/{$team->slug}/inventory/categories", [
            'name' => 'Snacks',
            'type' => 'MINI_SHOP',
            'description' => 'Quick sell snack products',
        ])
        ->assertRedirect("/{$team->slug}/inventory");

    $this->assertDatabaseHas('inventory_categories', [
        'team_id' => $team->id,
        'name' => 'Snacks',
        'type' => 'MINI_SHOP',
    ]);
});

test('admins can update inventory categories', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Kitchen',
        'type' => 'KITCHEN',
    ]);

    $this->actingAs($user)
        ->patch("/{$team->slug}/inventory/categories/{$category->id}", [
            'name' => 'Main Kitchen',
            'type' => 'KITCHEN',
            'description' => 'Updated kitchen category',
        ])
        ->assertRedirect("/{$team->slug}/inventory");

    $this->assertDatabaseHas('inventory_categories', [
        'id' => $category->id,
        'name' => 'Main Kitchen',
        'description' => 'Updated kitchen category',
    ]);
});

test('admins can delete empty inventory categories', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Store',
        'type' => 'STORE',
    ]);

    $this->actingAs($user)
        ->delete("/{$team->slug}/inventory/categories/{$category->id}")
        ->assertRedirect("/{$team->slug}/inventory");

    $this->assertModelMissing($category);
});

test('admins cannot delete category that still has items', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Bar',
        'type' => 'BAR',
    ]);

    InventoryItem::query()->create([
        'team_id' => $team->id,
        'inventory_category_id' => $category->id,
        'name' => 'Beer',
        'unit_price' => 500,
        'unit' => 'bottle',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->delete("/{$team->slug}/inventory/categories/{$category->id}")
        ->assertRedirect("/{$team->slug}/inventory");

    $this->assertModelExists($category);
});

test('members cannot manage categories', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $category = InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Mini-Shop',
        'type' => 'MINI_SHOP',
    ]);

    $this->actingAs($user)
        ->post("/{$team->slug}/inventory/categories", [
            'name' => 'Forbidden Category',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch("/{$team->slug}/inventory/categories/{$category->id}", [
            'name' => 'Forbidden Update',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete("/{$team->slug}/inventory/categories/{$category->id}")
        ->assertForbidden();
});

test('category names are unique per team', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    InventoryCategory::query()->create([
        'team_id' => $team->id,
        'name' => 'Kitchen',
        'type' => 'KITCHEN',
    ]);

    $this->actingAs($user)
        ->post("/{$team->slug}/inventory/categories", [
            'name' => 'Kitchen',
            'type' => 'KITCHEN',
        ])
        ->assertSessionHasErrors('name');
});

test('cannot manage categories from another team', function () {
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
        ->patch("/{$team->slug}/inventory/categories/{$otherTeamCategory->id}", [
            'name' => 'Invalid Category',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete("/{$team->slug}/inventory/categories/{$otherTeamCategory->id}")
        ->assertForbidden();
});
