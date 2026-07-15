<?php

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('global search returns matching guests, bookings, and rooms', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    Guest::factory()->create([
        'team_id' => $team->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
    $room = Room::factory()->create(['team_id' => $team->id, 'room_number' => 'ADA-1']);
    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'guest_name' => 'Ada Party',
    ]);

    $response = $this->actingAs($user)->getJson("/{$team->slug}/search?q=Ada");

    $response->assertOk();
    $types = collect($response->json('results'))->pluck('type')->unique()->sort()->values();
    expect($types)->toContain('Guest');
    expect($types)->toContain('Booking');
});

test('global search ignores very short queries', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->getJson("/{$team->slug}/search?q=a")
        ->assertOk()
        ->assertJson(['results' => []]);
});

test('pos-only staff cannot use global search', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'pos']);

    $this->actingAs($user)
        ->get("/{$team->slug}/search?q=Ada")
        ->assertForbidden();
});
