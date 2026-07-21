<?php

use App\Enums\Ability;
use App\Models\Department;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Support\BookingStayService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function hotelTeam(): Team
{
    return Team::factory()->create([
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
        'early_check_in_from' => '08:00',
    ]);
}

/**
 * The settlement the desk takes when the guest leaves.
 *
 * @return array<string, mixed>
 */
function settlement(float $amount, string $date = '2026-05-11'): array
{
    return [
        'settlement_amount' => $amount,
        'settlement_method' => 'cash',
        'settlement_payment_date' => $date,
    ];
}

/**
 * A three-night stay, checked in, priced by the policy.
 */
function threeNightStay(Team $team, Room $room)
{
    $booking = $team->bookings()->create([
        'room_id' => $room->id,
        'guest_name' => 'Ada Guest',
        'guest_email' => 'ada@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-05-10',
        'check_out_date' => '2026-05-13',
        'check_in_at' => '2026-05-10 14:00',
        'check_out_at' => '2026-05-13 12:00',
        'price_per_night' => $room->price_per_night,
        'total_amount' => 0,
        'status' => 'checked_in',
    ]);

    app(BookingStayService::class)->apply($team, $booking);

    return $booking->fresh();
}

test('front desk can check a guest out early and it records when they left', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000, 'status' => 'occupied']);
    $booking = threeNightStay($team, $room);

    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $clerk = User::factory()->create();
    $clerk->teams()->attach($team, ['role' => 'member']);
    $clerk->departments()->attach($frontDesk, ['team_id' => $team->id]);

    $this->travelTo('2026-05-11 09:30');

    $this->actingAs($clerk)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(30000))
        ->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe('checked_out');
    expect($booking->checked_out_at->toDateTimeString())->toBe('2026-05-11 09:30:00');
    expect($booking->departedEarly())->toBeTrue();
    expect($booking->unusedNights())->toBe(2);
});

test('an early checkout frees the room for the nights the guest gave back', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000, 'status' => 'occupied']);
    $booking = threeNightStay($team, $room);

    // While the stay is live the room is not sellable for those nights.
    expect(Room::query()->whereKey($room->id)
        ->whereDoesntHave('bookings', fn ($q) => $q->overlapping('2026-05-11', '2026-05-13'))
        ->exists())->toBeFalse();

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-11 09:30');
    $this->actingAs($manager)->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(30000));

    // Once they have gone, the same nights can be resold.
    expect(Room::query()->whereKey($room->id)
        ->whereDoesntHave('bookings', fn ($q) => $q->overlapping('2026-05-11', '2026-05-13'))
        ->exists())->toBeTrue();

    expect($room->fresh()->status)->toBe('available');
});

test('leaving early does not quietly reduce the bill', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000, 'status' => 'occupied']);
    $booking = threeNightStay($team, $room);

    expect($booking->chargeable_nights)->toBe(3);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-11 09:30');
    $this->actingAs($manager)->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(30000));

    $booking->refresh();

    // The room was held for the whole stay, so the folio stands until somebody
    // decides otherwise.
    expect($booking->chargeable_nights)->toBe(3);
    expect((float) $booking->total_amount)->toBe(30000.0);
    expect((float) $booking->invoice->total_amount)->toBe(30000.0);
});

test('a manager can waive the unused nights after an early checkout', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000, 'status' => 'occupied']);
    $booking = threeNightStay($team, $room);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-11 09:30');

    // The desk waives the unused nights first, then takes what is now owed.
    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
            'requested_nights' => 1,
            'reason' => 'Guest left two nights early, goodwill',
        ])->assertRedirect();

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(10000))
        ->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe('checked_out');

    expect($booking->chargeable_nights)->toBe(1);
    expect((float) $booking->invoice->fresh()->total_amount)->toBe(10000.0);
});

test('a departure cannot be recorded in the future', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000]);
    $booking = threeNightStay($team, $room);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-11 09:30');

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout", [
            ...settlement(30000),
            'checked_out_at' => '2026-05-12 10:00',
        ])
        ->assertSessionHasErrors('checked_out_at');
});

test('a guest who stays the full course is not marked as leaving early', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000, 'status' => 'occupied']);
    $booking = threeNightStay($team, $room);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-13 12:00');
    $this->actingAs($manager)->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(30000, '2026-05-13'));

    $booking->refresh();

    expect($booking->departedEarly())->toBeFalse();
    expect($booking->unusedNights())->toBe(0);
});

test('front desk needs booking rights to check anyone out', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000]);
    $booking = threeNightStay($team, $room);

    // Bar staff manage no bookings.
    $bar = Department::factory()->for($team)->create(['name' => 'Main Bar']);
    $barman = User::factory()->create();
    $barman->teams()->attach($team, ['role' => 'member', 'data_scope' => 'departments']);
    $barman->departments()->attach($bar, ['team_id' => $team->id]);

    expect($barman->hasAbility(Ability::ManageBookings, $team))->toBeFalse();

    $this->actingAs($barman)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout")
        ->assertForbidden();

    expect($booking->fresh()->status)->toBe('checked_in');
});

test('a guest can be checked out straight from a reservation that was never checked in', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000, 'status' => 'occupied']);
    $booking = threeNightStay($team, $room);

    // Never formally checked in — the common case for an advance reservation.
    $booking->update(['status' => 'confirmed']);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-11 09:30');

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(30000))
        ->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe('checked_out');
    expect($booking->checked_out_at->toDateTimeString())->toBe('2026-05-11 09:30:00');
    expect($room->fresh()->status)->toBe('available');
});

test('a pending reservation can also be closed out', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000]);
    $booking = threeNightStay($team, $room);
    $booking->update(['status' => 'pending']);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-11 09:30');

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(30000))
        ->assertRedirect();

    expect($booking->fresh()->status)->toBe('checked_out');
});

test('a booking that is already checked out cannot be checked out twice', function () {
    $team = hotelTeam();
    $room = Room::factory()->for($team)->create(['price_per_night' => 10000]);
    $booking = threeNightStay($team, $room);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->travelTo('2026-05-11 09:30');
    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(30000));

    $firstDeparture = $booking->fresh()->checked_out_at;

    $this->travelTo('2026-05-11 15:00');
    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/checkout", settlement(0))
        ->assertSessionHasErrors('settlement_amount');

    // The original departure stands.
    expect($booking->fresh()->checked_out_at->toDateTimeString())
        ->toBe($firstDeparture->toDateTimeString());
});
