<?php

use App\Enums\Ability;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Support\BookingStayService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A hotel on the standard clock, with a room at a round nightly rate.
 *
 * @return array{0: Team, 1: Room}
 */
function hotelWithRoom(float $rate = 10000): array
{
    $team = Team::factory()->create([
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
        'early_check_in_from' => '08:00',
    ]);

    $room = Room::factory()->for($team)->create(['price_per_night' => $rate]);

    return [$team, $room];
}

function bookingBetween(Team $team, Room $room, string $arrival, string $departure): Booking
{
    return $team->bookings()->create([
        'room_id' => $room->id,
        'guest_name' => 'Ada Guest',
        'guest_email' => 'ada@example.com',
        'number_of_guests' => 1,
        'check_in_date' => substr($arrival, 0, 10),
        'check_out_date' => substr($departure, 0, 10),
        'check_in_at' => $arrival,
        'check_out_at' => $departure,
        'price_per_night' => $room->price_per_night,
        'total_amount' => 0,
        'status' => 'confirmed',
    ]);
}

test('a standard stay is priced at one night', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 14:00', '2026-03-11 12:00');

    app(BookingStayService::class)->apply($team, $booking);

    expect($booking->fresh()->chargeable_nights)->toBe(1);
    expect((float) $booking->fresh()->total_amount)->toBe(10000.0);
});

test('an early arrival rolling over to the next day is priced at two nights', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');

    app(BookingStayService::class)->apply($team, $booking);

    expect($booking->fresh()->chargeable_nights)->toBe(2);
    expect((float) $booking->fresh()->total_amount)->toBe(20000.0);
});

test('a pre-dawn arrival is priced at two nights and says why', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 05:00', '2026-03-10 12:00');

    app(BookingStayService::class)->apply($team, $booking);

    $booking->refresh();

    expect($booking->chargeable_nights)->toBe(2);
    expect($booking->nights_basis)->toContain('before the 08:00 early check-in time');
});

test('the desk requesting a rollover leaves the bill alone until approved', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');
    app(BookingStayService::class)->apply($team, $booking);

    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $clerk = User::factory()->create();
    $clerk->teams()->attach($team, ['role' => 'member']);
    $clerk->departments()->attach($frontDesk, ['team_id' => $team->id]);

    expect($clerk->hasAbility(Ability::ReviewStayAdjustments, $team))->toBeFalse();

    $this->actingAs($clerk)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
            'requested_nights' => 1,
            'reason' => 'Guest arrived early, agreed one night',
        ])
        ->assertRedirect();

    $booking->refresh();

    expect($booking->pendingStayAdjustment())->not->toBeNull();
    expect($booking->chargeable_nights)->toBe(2);
    expect((float) $booking->total_amount)->toBe(20000.0);
});

test('a manager approving the rollover re-prices it to one night', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');
    app(BookingStayService::class)->apply($team, $booking);

    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $clerk = User::factory()->create();
    $clerk->teams()->attach($team, ['role' => 'member']);
    $clerk->departments()->attach($frontDesk, ['team_id' => $team->id]);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($clerk)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
            'requested_nights' => 1,
        ]);

    $adjustment = $booking->fresh()->pendingStayAdjustment();

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments/{$adjustment->id}/approve")
        ->assertRedirect();

    $booking->refresh();

    expect($booking->chargeable_nights)->toBe(1);
    expect((float) $booking->total_amount)->toBe(10000.0);
    expect($booking->nights_basis)->toContain('manager approval');
});

test('rejecting the request leaves the policy price standing', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 05:00', '2026-03-10 12:00');
    app(BookingStayService::class)->apply($team, $booking);

    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $clerk = User::factory()->create();
    $clerk->teams()->attach($team, ['role' => 'member']);
    $clerk->departments()->attach($frontDesk, ['team_id' => $team->id]);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($clerk)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", ['requested_nights' => 1]);

    $adjustment = $booking->fresh()->pendingStayAdjustment();

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments/{$adjustment->id}/reject")
        ->assertRedirect();

    $booking->refresh();

    expect($booking->chargeable_nights)->toBe(2);
    expect((float) $booking->total_amount)->toBe(20000.0);
});

test('a manager asking for a different night count is applied straight away', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');
    app(BookingStayService::class)->apply($team, $booking);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", ['requested_nights' => 1]);

    $booking->refresh();

    expect($booking->pendingStayAdjustment())->toBeNull();
    expect($booking->chargeable_nights)->toBe(1);
});

test('a clerk without the review ability cannot approve their own request', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');
    app(BookingStayService::class)->apply($team, $booking);

    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $clerk = User::factory()->create();
    $clerk->teams()->attach($team, ['role' => 'member']);
    $clerk->departments()->attach($frontDesk, ['team_id' => $team->id]);

    $this->actingAs($clerk)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", ['requested_nights' => 1]);

    $adjustment = $booking->fresh()->pendingStayAdjustment();

    $this->actingAs($clerk)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments/{$adjustment->id}/approve")
        ->assertForbidden();

    expect($booking->fresh()->chargeable_nights)->toBe(2);
});

test('an approved night count survives the stay being extended', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');
    $stays = app(BookingStayService::class);
    $stays->apply($team, $booking);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    // Manager waives the extra night for the early arrival.
    $stays->requestAdjustment($team, $booking->fresh(), $manager, 1);

    expect($booking->fresh()->chargeable_nights)->toBe(1);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/extend-stay", [
            'check_out_date' => '2026-03-13',
        ])
        ->assertRedirect();

    // The waiver stands rather than being silently undone by the extension.
    expect($booking->fresh()->chargeable_nights)->toBe(1);
});

test('the finance department can review night counts out of the box', function () {
    [$team] = hotelWithRoom();
    $finance = Department::factory()->for($team)->create(['name' => 'Finance']);

    $accountant = User::factory()->create();
    $accountant->teams()->attach($team, ['role' => 'member']);
    $accountant->departments()->attach($finance, ['team_id' => $team->id]);

    expect($accountant->hasAbility(Ability::ReviewStayAdjustments, $team))->toBeTrue();
});

test('the booking form can quote a stay before it is created', function () {
    [$team, $room] = hotelWithRoom(15000);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $response = $this->actingAs($manager)->getJson(
        "/{$team->slug}/bookings/quote?"
        .http_build_query([
            'check_in_at' => '2026-03-10 05:00',
            'check_out_at' => '2026-03-10 12:00',
            'room_id' => $room->id,
        ])
    );

    $response->assertOk()
        ->assertJsonPath('nights', 2)
        ->assertJsonPath('consumed_previous_night', true)
        ->assertJsonPath('total', 30000)
        ->assertJsonPath('policy.early_check_in_from', '08:00');

    expect($response->json('basis'))->toContain('before the 08:00 early check-in time');
});

test('the quote follows the same rule as the booking it precedes', function () {
    [$team, $room] = hotelWithRoom(15000);

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $quoted = $this->actingAs($manager)->getJson(
        "/{$team->slug}/bookings/quote?"
        .http_build_query([
            'check_in_at' => '2026-03-10 08:00',
            'check_out_at' => '2026-03-11 12:00',
            'room_id' => $room->id,
        ])
    )->json();

    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');
    app(BookingStayService::class)->apply($team, $booking);

    // The desk is quoted exactly what the guest ends up billed.
    expect($quoted['nights'])->toBe($booking->fresh()->chargeable_nights);
    expect((float) $quoted['total'])->toBe((float) $booking->fresh()->total_amount);
});

test('approving fewer nights updates the invoice the guest actually pays', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    // Create the invoice the way the booking flow does.
    $this->actingAs($manager)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Ada Guest',
        'guest_email' => 'ada@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-03-12',
        'check_out_date' => '2026-03-13',
        'check_in_at' => '2026-03-12 08:00',
        'check_out_at' => '2026-03-13 12:00',
        'status' => 'confirmed',
    ])->assertRedirect();

    $created = $team->bookings()->latest('id')->first();

    expect($created->chargeable_nights)->toBe(2);
    expect((float) $created->invoice->total_amount)->toBe(20000.0);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$created->id}/stay-adjustments", [
            'requested_nights' => 1,
        ])->assertRedirect();

    $created->refresh();

    expect($created->chargeable_nights)->toBe(1);
    // The bill, not just the booking row, has to follow the approval.
    expect((float) $created->invoice->fresh()->total_amount)->toBe(10000.0);
});

test('front desk sees the request control and the manager sees the review one', function () {
    [$team, $room] = hotelWithRoom();
    $booking = bookingBetween($team, $room, '2026-03-10 05:00', '2026-03-10 12:00');
    app(BookingStayService::class)->apply($team, $booking);

    $frontDesk = Department::factory()->for($team)->create(['name' => 'Front Desk']);
    $clerk = User::factory()->create();
    $clerk->teams()->attach($team, ['role' => 'member']);
    $clerk->departments()->attach($frontDesk, ['team_id' => $team->id]);

    // The desk can open the list and is told this is a two-night stay and why.
    $this->actingAs($clerk)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canReviewStayAdjustments', false)
            ->where('stayPolicy.early_check_in_from', '08:00')
            ->where('bookings.0.chargeable_nights', 2)
            ->where('bookings.0.active_stay_adjustment', null)
        );

    $this->actingAs($clerk)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
            'requested_nights' => 1,
            'reason' => 'Guest agreed one night',
        ])->assertRedirect();

    // A manager now sees the pending request on the same row.
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canReviewStayAdjustments', true)
            ->where('bookings.0.active_stay_adjustment.computed_nights', 2)
            ->where('bookings.0.active_stay_adjustment.requested_nights', 1)
            ->where('bookings.0.active_stay_adjustment.requested_by.name', $clerk->name)
        );
});

test('the booking form offers reservation or check in, not pending and confirmed', function () {
    [$team] = hotelWithRoom();

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('creatableStatuses.0.value', 'pending')
            ->where('creatableStatuses.0.label', 'Reservation')
            ->where('creatableStatuses.1.value', 'checked_in')
            ->where('creatableStatuses.1.label', 'Checked In')
            ->count('creatableStatuses', 2)
            ->where('statusLabels.pending', 'Reservation')
            ->where('statusLabels.checked_in', 'Checked In')
        );
});

test('checking a walk-in in at the desk occupies the room and prices the arrival', function () {
    [$team, $room] = hotelWithRoom();

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    // A guest who turns up at 05:00 and is checked in there and then.
    $this->actingAs($manager)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Walk In',
        'guest_email' => 'walkin@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-04-02',
        'check_out_date' => '2026-04-03',
        'check_in_at' => '2026-04-02 05:00',
        'check_out_at' => '2026-04-02 12:00',
        'status' => 'checked_in',
    ])->assertRedirect();

    $booking = $team->bookings()->latest('id')->first();

    expect($booking->status)->toBe('checked_in');
    expect($room->fresh()->status)->toBe('occupied');
    expect($booking->chargeable_nights)->toBe(2);
    expect($booking->nights_basis)->toContain('before the 08:00 early check-in time');
});
