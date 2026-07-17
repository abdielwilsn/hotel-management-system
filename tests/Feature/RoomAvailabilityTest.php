<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Team, 1: User}
 */
function availabilityActors(string $role = 'member'): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => $role]);

    return [$team, $user];
}

test('receptionists can list rooms that are free for a date range', function () {
    [$team, $user] = availabilityActors();

    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-06-01&check_out_date=2026-06-05")
        ->assertOk()
        ->assertJsonCount(1, 'rooms')
        ->assertJsonPath('rooms.0.id', $room->id);
});

test('a room already booked for the range is not offered', function () {
    [$team, $user] = availabilityActors();

    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-06-03&check_out_date=2026-06-07")
        ->assertOk()
        ->assertJsonCount(0, 'rooms');
});

test('a room occupied today is still offered for a future range', function () {
    [$team, $user] = availabilityActors();

    // Currently occupied, but that stay ends long before the requested range.
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
        'status' => 'checked_in',
    ]);

    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-12-01&check_out_date=2026-12-05")
        ->assertOk()
        ->assertJsonCount(1, 'rooms')
        ->assertJsonPath('rooms.0.id', $room->id);
});

test('the checkout day is free for the next guest', function () {
    [$team, $user] = availabilityActors();

    Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => Room::query()->where('team_id', $team->id)->value('id'),
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'confirmed',
    ]);

    // Checking in on the previous guest's checkout day must be allowed.
    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-06-05&check_out_date=2026-06-10")
        ->assertOk()
        ->assertJsonCount(1, 'rooms');
});

test('rooms under maintenance are never offered', function () {
    [$team, $user] = availabilityActors();

    Room::factory()->create(['team_id' => $team->id, 'status' => 'maintenance']);

    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-06-01&check_out_date=2026-06-05")
        ->assertOk()
        ->assertJsonCount(0, 'rooms');
});

test('cancelled bookings do not block availability', function () {
    [$team, $user] = availabilityActors();

    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'cancelled',
    ]);

    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-06-01&check_out_date=2026-06-05")
        ->assertOk()
        ->assertJsonCount(1, 'rooms');
});

test('availability only covers the current team', function () {
    [$team, $user] = availabilityActors();
    $otherTeam = Team::factory()->create();

    Room::factory()->create(['team_id' => $otherTeam->id, 'status' => 'available']);

    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-06-01&check_out_date=2026-06-05")
        ->assertOk()
        ->assertJsonCount(0, 'rooms');
});

test('the date range is validated', function () {
    [$team, $user] = availabilityActors();

    $this->actingAs($user)
        ->getJson("/{$team->slug}/rooms/availability?check_in_date=2026-06-05&check_out_date=2026-06-01")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('check_out_date');
});
