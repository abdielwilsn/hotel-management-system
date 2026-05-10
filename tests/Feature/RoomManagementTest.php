<?php

use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('team members can view the rooms index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $response = $this->actingAs($user)->get("/{$team->slug}/rooms");

    $response->assertStatus(200);
});

test('admins can create rooms', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $response = $this->actingAs($user)->post("/{$team->slug}/rooms", [
        'room_number' => '101',
        'floor' => 1,
        'room_type' => 'double',
        'capacity' => 2,
        'price_per_night' => 150.00,
        'status' => 'available',
        'description' => 'Comfortable double room',
    ]);

    $response->assertRedirect("/{$team->slug}/rooms");
    $this->assertDatabaseHas('rooms', [
        'room_number' => '101',
        'floor' => 1,
        'team_id' => $team->id,
    ]);
});

test('admins can update rooms', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->patch("/{$team->slug}/rooms/{$room->id}", [
        'room_number' => '202',
        'floor' => 2,
        'room_type' => 'suite',
        'capacity' => 4,
        'price_per_night' => 250.00,
        'status' => 'available',
        'description' => 'Spacious suite',
    ]);

    $response->assertRedirect("/{$team->slug}/rooms");
    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'room_number' => '202',
        'floor' => 2,
        'room_type' => 'suite',
    ]);
});

test('admins can delete rooms', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/rooms/{$room->id}");

    $response->assertRedirect("/{$team->slug}/rooms");
    $this->assertModelMissing($room);
});

test('members cannot create rooms', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $response = $this->actingAs($user)->post("/{$team->slug}/rooms", [
        'room_number' => '101',
        'floor' => 1,
        'room_type' => 'double',
        'capacity' => 2,
        'price_per_night' => 150.00,
        'status' => 'available',
    ]);

    $response->assertStatus(403);
});

test('room number must be unique per team', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $existingRoom = Room::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/rooms", [
        'room_number' => $existingRoom->room_number,
        'floor' => 1,
        'room_type' => 'double',
        'capacity' => 2,
        'price_per_night' => 150.00,
        'status' => 'available',
    ]);

    $response->assertSessionHasErrors('room_number');
});

test('room status must be valid', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $response = $this->actingAs($user)->post("/{$team->slug}/rooms", [
        'room_number' => '101',
        'floor' => 1,
        'room_type' => 'double',
        'capacity' => 2,
        'price_per_night' => 150.00,
        'status' => 'invalid_status',
    ]);

    $response->assertSessionHasErrors('status');
});
