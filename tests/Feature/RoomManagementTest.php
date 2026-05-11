<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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

test('rooms index shows active booking details for occupied rooms', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $room = Room::factory()->create([
        'team_id' => $team->id,
        'status' => 'available',
    ]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'guest_name' => 'Occupied Guest',
        'status' => 'checked_in',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/rooms")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('occupancySummary.occupied_rooms', 1)
            ->where('occupancySummary.reserved_rooms', 0)
            ->where('rooms.0.id', $room->id)
            ->where('rooms.0.status', 'occupied')
            ->where('rooms.0.active_booking.id', $booking->id)
            ->where('rooms.0.active_booking.guest_name', 'Occupied Guest'));
});

test('rooms index marks active reservations today as reserved', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $reservedRoom = Room::factory()->create([
        'team_id' => $team->id,
        'status' => 'available',
    ]);

    $futureRoom = Room::factory()->create([
        'team_id' => $team->id,
        'status' => 'available',
    ]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $reservedRoom->id,
        'guest_name' => 'Reserved Guest',
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
        'status' => 'confirmed',
    ]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $futureRoom->id,
        'guest_name' => 'Future Guest',
        'check_in_date' => now()->addDays(3)->toDateString(),
        'check_out_date' => now()->addDays(5)->toDateString(),
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/rooms")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('occupancySummary.reserved_rooms', 1)
            ->where('occupancySummary.active_reservations', 1)
            ->where('rooms', function ($rooms) use ($reservedRoom, $futureRoom): bool {
                $reserved = collect($rooms)->firstWhere('id', $reservedRoom->id);
                $future = collect($rooms)->firstWhere('id', $futureRoom->id);

                return $reserved !== null
                    && $reserved['status'] === 'reserved'
                    && ($reserved['active_booking']['guest_name'] ?? null) === 'Reserved Guest'
                    && $future !== null
                    && $future['status'] === 'available';
            }));
});

test('rooms index can be filtered by room type and status', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $reservedRoom = Room::factory()->create([
        'team_id' => $team->id,
        'room_type' => 'suite',
        'status' => 'available',
    ]);

    $availableRoom = Room::factory()->create([
        'team_id' => $team->id,
        'room_type' => 'suite',
        'status' => 'available',
    ]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $reservedRoom->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/rooms?room_type=suite&status=reserved")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rooms', fn ($rooms) => count($rooms) === 1)
            ->where('rooms.0.id', $reservedRoom->id)
            ->where('rooms.0.status', 'reserved')
            ->where('filters.room_type', 'suite')
            ->where('filters.status', 'reserved'));

    expect($availableRoom->id)->not->toBe($reservedRoom->id);
});
