<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('members cannot view reports', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/reports")
        ->assertForbidden();
});

test('admins can view reports', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->get("/{$team->slug}/reports")
        ->assertOk();
});

test('members cannot view forecasts', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/forecasts")
        ->assertForbidden();
});

test('admins can view forecasts', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->get("/{$team->slug}/forecasts")
        ->assertOk();
});
