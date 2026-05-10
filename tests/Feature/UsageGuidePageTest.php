<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('team members can view the usage guide page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/usage-guide")
        ->assertOk();
});
