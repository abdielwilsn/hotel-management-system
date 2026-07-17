<?php

use App\Models\Room;
use App\Models\RoomType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Team, 1: User}
 */
function roomTypeActors(string $role = 'admin'): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => $role]);

    return [$team, $user];
}

test('every new team starts with the standard room types', function () {
    $team = Team::factory()->create();

    expect($team->roomTypes()->pluck('slug')->sort()->values()->all())
        ->toBe(['deluxe', 'double', 'penthouse', 'single', 'suite']);
});

test('managers can add a room type', function () {
    [$team, $user] = roomTypeActors();

    $this->actingAs($user)
        ->post("/{$team->slug}/room-types", ['name' => 'Cabana'])
        ->assertRedirect();

    $this->assertDatabaseHas('room_types', [
        'team_id' => $team->id,
        'name' => 'Cabana',
        'slug' => 'cabana',
    ]);
});

test('a room can be created with a manager-defined type', function () {
    [$team, $user] = roomTypeActors();

    $team->roomTypes()->create(['name' => 'Cabana', 'slug' => 'cabana']);

    $this->actingAs($user)
        ->post("/{$team->slug}/rooms", [
            'room_number' => '501',
            'floor' => 5,
            'room_type' => 'cabana',
            'capacity' => 2,
            'price_per_night' => 100,
            'status' => 'available',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('rooms', [
        'team_id' => $team->id,
        'room_number' => '501',
        'room_type' => 'cabana',
    ]);
});

test('a room cannot use a type the team has not defined', function () {
    [$team, $user] = roomTypeActors();

    $this->actingAs($user)
        ->post("/{$team->slug}/rooms", [
            'room_number' => '502',
            'floor' => 5,
            'room_type' => 'igloo',
            'capacity' => 2,
            'price_per_night' => 100,
            'status' => 'available',
        ])
        ->assertSessionHasErrors('room_type');
});

test('managers can rename a room type without breaking existing rooms', function () {
    [$team, $user] = roomTypeActors();

    $type = $team->roomTypes()->where('slug', 'suite')->first();
    $room = Room::factory()->create(['team_id' => $team->id, 'room_type' => 'suite']);

    $this->actingAs($user)
        ->patch("/{$team->slug}/room-types/{$type->id}", ['name' => 'Executive Suite'])
        ->assertRedirect();

    // Renaming only touches the label; the slug rooms point at is untouched.
    expect($type->fresh()->name)->toBe('Executive Suite')
        ->and($type->fresh()->slug)->toBe('suite')
        ->and($room->fresh()->room_type)->toBe('suite');
});

test('room type names must be unique within a team', function () {
    [$team, $user] = roomTypeActors();

    $this->actingAs($user)
        ->post("/{$team->slug}/room-types", ['name' => 'Suite'])
        ->assertSessionHasErrors('name');
});

test('managers can delete an unused room type', function () {
    [$team, $user] = roomTypeActors();

    $type = $team->roomTypes()->where('slug', 'penthouse')->first();

    $this->actingAs($user)
        ->delete("/{$team->slug}/room-types/{$type->id}")
        ->assertRedirect();

    $this->assertModelMissing($type);
});

test('a room type still in use cannot be deleted', function () {
    [$team, $user] = roomTypeActors();

    $type = $team->roomTypes()->where('slug', 'double')->first();
    Room::factory()->create(['team_id' => $team->id, 'room_type' => 'double']);

    $this->actingAs($user)
        ->delete("/{$team->slug}/room-types/{$type->id}")
        ->assertSessionHasErrors('room_type');

    $this->assertModelExists($type);
});

test('receptionists cannot manage room types', function () {
    [$team, $user] = roomTypeActors('member');

    $type = $team->roomTypes()->where('slug', 'suite')->first();

    $this->actingAs($user)
        ->post("/{$team->slug}/room-types", ['name' => 'Cabana'])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch("/{$team->slug}/room-types/{$type->id}", ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete("/{$team->slug}/room-types/{$type->id}")
        ->assertForbidden();
});

test('a team cannot touch another teams room types', function () {
    [$team, $user] = roomTypeActors();
    $otherTeam = Team::factory()->create();
    $foreignType = $otherTeam->roomTypes()->first();

    $this->actingAs($user)
        ->patch("/{$team->slug}/room-types/{$foreignType->id}", ['name' => 'Hijacked'])
        ->assertNotFound();
});

test('duplicate names get a unique slug', function () {
    $team = Team::factory()->create();

    // "Single" already exists, so a second one must not clash on slug.
    $slug = RoomType::uniqueSlugFor($team, 'Single');

    expect($slug)->toBe('single-2');
});
