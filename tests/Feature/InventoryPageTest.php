<?php

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('managers can view inventory page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->get("/{$team->slug}/inventory")
        ->assertOk();
});

test('receptionists cannot view inventory page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/inventory")
        ->assertForbidden();
});

test('inventory page only shows current team data', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
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
        'name' => 'Heineken',
        'unit_price' => 900,
        'unit' => 'bottle',
    ]);

    $otherCategory = InventoryCategory::query()->create([
        'team_id' => $otherTeam->id,
        'name' => 'Store',
        'type' => 'STORE',
    ]);

    InventoryItem::query()->create([
        'team_id' => $otherTeam->id,
        'inventory_category_id' => $otherCategory->id,
        'name' => 'Toilet Tissue',
        'unit_price' => 0,
        'unit' => 'roll',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/inventory")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.categories', 1)
            ->where('summary.items', 1));
});
