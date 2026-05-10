<?php

use App\Models\Guest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('team members can view guests index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/guests")
        ->assertOk();
});

test('admins can create guests', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->post("/{$team->slug}/guests", [
            'first_name' => 'Ava',
            'last_name' => 'Morgan',
            'email' => 'ava@example.com',
            'phone' => '+1 555-0101',
            'date_of_birth' => '1994-03-15',
            'loyalty_tier' => 'gold',
            'loyalty_points' => 1200,
            'last_stay_date' => now()->subDays(20)->toDateString(),
            'preferences' => 'High floor, no feather pillows',
            'notes' => 'Prefers contactless check-in',
        ])
        ->assertRedirect("/{$team->slug}/guests");

    $this->assertDatabaseHas('guests', [
        'team_id' => $team->id,
        'email' => 'ava@example.com',
        'loyalty_tier' => 'gold',
    ]);
});

test('admins can update guests', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $guest = Guest::factory()->create([
        'team_id' => $team->id,
        'first_name' => 'Liam',
        'last_name' => 'Reed',
        'email' => 'liam@example.com',
        'loyalty_tier' => 'standard',
    ]);

    $this->actingAs($user)
        ->patch("/{$team->slug}/guests/{$guest->id}", [
            'first_name' => 'Liam',
            'last_name' => 'Reed',
            'email' => 'liam@example.com',
            'phone' => '+1 555-0199',
            'date_of_birth' => '1990-01-01',
            'loyalty_tier' => 'silver',
            'loyalty_points' => 400,
            'last_stay_date' => now()->subDays(10)->toDateString(),
            'preferences' => 'Near elevator',
            'notes' => 'Returning corporate traveler',
        ])
        ->assertRedirect("/{$team->slug}/guests");

    $this->assertDatabaseHas('guests', [
        'id' => $guest->id,
        'loyalty_tier' => 'silver',
        'loyalty_points' => 400,
    ]);
});

test('admins can delete guests', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $guest = Guest::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete("/{$team->slug}/guests/{$guest->id}")
        ->assertRedirect("/{$team->slug}/guests");

    $this->assertModelMissing($guest);
});

test('members can create guests', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->post("/{$team->slug}/guests", [
            'first_name' => 'Una',
            'last_name' => 'Member',
            'email' => 'una@example.com',
            'loyalty_tier' => 'standard',
            'loyalty_points' => 0,
        ])
        ->assertRedirect("/{$team->slug}/guests");

    $this->assertDatabaseHas('guests', [
        'team_id' => $team->id,
        'email' => 'una@example.com',
    ]);
});

test('members can update guests', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $guest = Guest::factory()->create([
        'team_id' => $team->id,
        'first_name' => 'Member',
        'last_name' => 'Guest',
        'email' => 'member.guest@example.com',
    ]);

    $this->actingAs($user)
        ->patch("/{$team->slug}/guests/{$guest->id}", [
            'first_name' => 'Member',
            'last_name' => 'Guest',
            'email' => 'member.guest@example.com',
            'phone' => '+1 555-0115',
            'date_of_birth' => '1991-05-10',
            'loyalty_tier' => 'silver',
            'loyalty_points' => 150,
            'last_stay_date' => now()->subDays(5)->toDateString(),
            'preferences' => 'Quiet room',
            'notes' => 'Updated by front desk',
        ])
        ->assertRedirect("/{$team->slug}/guests");

    $this->assertDatabaseHas('guests', [
        'id' => $guest->id,
        'loyalty_tier' => 'silver',
        'loyalty_points' => 150,
    ]);
});

test('members cannot delete guests', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $guest = Guest::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete("/{$team->slug}/guests/{$guest->id}")
        ->assertForbidden();

    $this->assertModelExists($guest);
});

test('email must be unique per team', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    Guest::factory()->create([
        'team_id' => $team->id,
        'email' => 'repeat@example.com',
    ]);

    $this->actingAs($user)
        ->post("/{$team->slug}/guests", [
            'first_name' => 'Dup',
            'last_name' => 'Email',
            'email' => 'repeat@example.com',
            'loyalty_tier' => 'standard',
            'loyalty_points' => 50,
        ])
        ->assertSessionHasErrors('email');
});

test('users cannot update guests from another team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $guest = Guest::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->patch("/{$team->slug}/guests/{$guest->id}", [
            'first_name' => 'Blocked',
            'last_name' => 'Update',
            'loyalty_tier' => 'standard',
            'loyalty_points' => 0,
        ])
        ->assertForbidden();
});

test('guests index can be filtered by loyalty tier and search', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $matchingGuest = Guest::factory()->create([
        'team_id' => $team->id,
        'first_name' => 'Lara',
        'last_name' => 'Stone',
        'loyalty_tier' => 'gold',
    ]);

    Guest::factory()->create([
        'team_id' => $team->id,
        'first_name' => 'Milo',
        'last_name' => 'Doe',
        'loyalty_tier' => 'standard',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/guests?loyalty_tier=gold&search=Lara")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('guests.0.id', $matchingGuest->id)
            ->where('guests', fn ($guests) => count($guests) === 1));
});
