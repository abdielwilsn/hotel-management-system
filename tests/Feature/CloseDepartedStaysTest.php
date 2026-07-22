<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A stay that ended in the past, optionally still owing money.
 */
function departedStay(Team $team, Room $room, float $total = 10000, float $paid = 10000): Booking
{
    $booking = $team->bookings()->create([
        'room_id' => $room->id,
        'guest_name' => 'Ada Guest',
        'guest_email' => 'ada@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-05-10',
        'check_out_date' => '2026-05-11',
        'check_in_at' => '2026-05-10 14:00',
        'check_out_at' => '2026-05-11 12:00',
        'price_per_night' => $total,
        'total_amount' => $total,
        'chargeable_nights' => 1,
        'status' => 'checked_in',
    ]);

    Invoice::factory()->for($team)->create([
        'booking_id' => $booking->id,
        'total_amount' => $total,
        'paid_amount' => $paid,
    ]);

    return $booking->fresh();
}

test('a settled stay past its departure is closed and the room handed back', function () {
    $team = Team::factory()->create();
    $room = Room::factory()->for($team)->create(['status' => 'occupied']);
    $booking = departedStay($team, $room);

    $this->travelTo('2026-05-12 08:00');

    $this->artisan('stays:close-departed')->assertSuccessful();

    $booking->refresh();

    expect($booking->status)->toBe('checked_out');
    // Recorded as leaving when they were due to, not when the job happened to run.
    expect($booking->checked_out_at->toDateTimeString())->toBe('2026-05-11 12:00:00');
    expect($room->fresh()->status)->toBe('available');
});

test('a stay that still owes money is left alone for the desk', function () {
    $team = Team::factory()->create();
    $room = Room::factory()->for($team)->create(['status' => 'occupied']);
    $booking = departedStay($team, $room, total: 10000, paid: 2500);

    $this->travelTo('2026-05-12 08:00');

    $this->artisan('stays:close-departed')
        ->expectsOutputToContain('still owe money')
        ->assertSuccessful();

    $booking->refresh();

    expect($booking->status)->toBe('checked_in');
    expect($room->fresh()->status)->toBe('occupied');
});

test('a stay that has not reached its departure is untouched', function () {
    $team = Team::factory()->create();
    $room = Room::factory()->for($team)->create(['status' => 'occupied']);
    $booking = departedStay($team, $room);

    $this->travelTo('2026-05-11 09:00');

    $this->artisan('stays:close-departed')->assertSuccessful();

    expect($booking->fresh()->status)->toBe('checked_in');
});

test('the dry run reports without changing anything', function () {
    $team = Team::factory()->create();
    $room = Room::factory()->for($team)->create(['status' => 'occupied']);
    $booking = departedStay($team, $room);

    $this->travelTo('2026-05-12 08:00');

    $this->artisan('stays:close-departed', ['--dry-run' => true])
        ->expectsOutputToContain('Would close')
        ->assertSuccessful();

    expect($booking->fresh()->status)->toBe('checked_in');
    expect($room->fresh()->status)->toBe('occupied');
});

test('a room still held by another stay is not handed back', function () {
    $team = Team::factory()->create();
    $room = Room::factory()->for($team)->create(['status' => 'occupied']);
    $departed = departedStay($team, $room);

    // The next guest is already booked into the same room.
    $team->bookings()->create([
        'room_id' => $room->id,
        'guest_name' => 'Next Guest',
        'guest_email' => 'next@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-05-11',
        'check_out_date' => '2026-05-14',
        'check_in_at' => '2026-05-11 14:00',
        'check_out_at' => '2026-05-14 12:00',
        'price_per_night' => 10000,
        'total_amount' => 30000,
        'status' => 'confirmed',
    ]);

    $this->travelTo('2026-05-12 08:00');
    $this->artisan('stays:close-departed')->assertSuccessful();

    expect($departed->fresh()->status)->toBe('checked_out');
    expect($room->fresh()->status)->toBe('occupied');
});

test('already closed and cancelled stays are ignored', function () {
    $team = Team::factory()->create();
    $room = Room::factory()->for($team)->create();

    $closed = departedStay($team, $room);
    $closed->update(['status' => 'checked_out', 'checked_out_at' => '2026-05-11 11:00']);

    $cancelled = departedStay($team, $room);
    $cancelled->update(['status' => 'cancelled']);

    $this->travelTo('2026-05-12 08:00');
    $this->artisan('stays:close-departed')->assertSuccessful();

    expect($closed->fresh()->checked_out_at->toDateTimeString())->toBe('2026-05-11 11:00:00');
    expect($cancelled->fresh()->status)->toBe('cancelled');
});
