<?php

use App\Models\PosOutlet;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function posTeamWithStaff(string $role = 'pos'): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => $role]);

    return [$team, $user];
}

test('pos staff are blocked from core hotel modules', function () {
    [$team, $user] = posTeamWithStaff();

    $this->actingAs($user)->get("/{$team->slug}/bookings")->assertForbidden();
    $this->actingAs($user)->get("/{$team->slug}/rooms")->assertForbidden();
    $this->actingAs($user)->get("/{$team->slug}/reports")->assertForbidden();
    $this->actingAs($user)->get("/{$team->slug}/dashboard")->assertForbidden();
});

test('members can still reach core hotel modules', function () {
    [$team, $user] = posTeamWithStaff('member');

    $this->actingAs($user)->get("/{$team->slug}/bookings")->assertOk();
});

test('pos staff can open the pos index', function () {
    [$team, $user] = posTeamWithStaff();

    $this->actingAs($user)->get("/{$team->slug}/pos")->assertOk();
});

test('pos staff can only operate outlets they are assigned to', function () {
    [$team, $user] = posTeamWithStaff();

    $assigned = PosOutlet::factory()->create(['team_id' => $team->id]);
    $other = PosOutlet::factory()->create(['team_id' => $team->id]);

    $user->posOutlets()->attach($assigned, ['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/{$team->slug}/pos/{$assigned->id}/terminal")
        ->assertOk();

    $this->actingAs($user)
        ->get("/{$team->slug}/pos/{$other->id}/terminal")
        ->assertForbidden();
});

test('members can operate any outlet without explicit assignment', function () {
    [$team, $user] = posTeamWithStaff('member');
    $outlet = PosOutlet::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/{$team->slug}/pos/{$outlet->id}/terminal")
        ->assertOk();
});

test('pos staff cannot reach outlet management', function () {
    [$team, $user] = posTeamWithStaff();

    $this->actingAs($user)->get("/{$team->slug}/pos/manage")->assertForbidden();
});

test('the pos index only lists outlets a pos user is assigned to', function () {
    [$team, $user] = posTeamWithStaff();

    $assigned = PosOutlet::factory()->create(['team_id' => $team->id, 'name' => 'My Bar']);
    PosOutlet::factory()->create(['team_id' => $team->id, 'name' => 'Hidden Restaurant']);
    $user->posOutlets()->attach($assigned, ['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/{$team->slug}/pos")
        ->assertInertia(fn ($page) => $page
            ->component('pos/Index')
            ->has('outlets', 1)
            ->where('outlets.0.name', 'My Bar'));
});
