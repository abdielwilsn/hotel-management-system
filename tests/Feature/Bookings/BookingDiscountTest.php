<?php

use App\Models\Booking;
use App\Models\BookingDiscount;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Team, 1: User, 2: Booking}
 */
function discountContext(string $role = 'member', float $total = 50000): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => $role]);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => $total,
        'status' => 'confirmed',
    ]);

    // Ensure the booking has its auto-generated invoice.
    $team->invoices()->create([
        'booking_id' => $booking->id,
        'invoice_number' => 'INV-'.$booking->id,
        'guest_name' => $booking->guest_name,
        'guest_email' => $booking->guest_email,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'subtotal' => $total,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => $total,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    return [$team, $user, $booking];
}

test('a front desk discount request is pending and does not change the bill', function () {
    [$team, $user, $booking] = discountContext('member');

    $this->actingAs($user)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts", [
            'type' => 'percentage',
            'value' => 10,
            'reason' => 'Regular guest',
        ])
        ->assertRedirect("/{$team->slug}/bookings");

    $this->assertDatabaseHas('booking_discounts', [
        'booking_id' => $booking->id,
        'status' => 'pending',
        'type' => 'percentage',
    ]);

    // Invoice untouched while pending.
    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'discount_amount' => 0,
        'total_amount' => 50000,
    ]);
});

test('a manager can approve a discount and the invoice total drops', function () {
    [$team, $manager, $booking] = discountContext('admin');

    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);

    $discount = BookingDiscount::factory()->percentage(10)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'requested_by_user_id' => $frontDesk->id,
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts/{$discount->id}/approve")
        ->assertRedirect("/{$team->slug}/bookings");

    expect($discount->fresh()->status)->toBe('approved');

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'discount_amount' => 5000,
        'total_amount' => 45000,
    ]);
});

test('a fixed discount is applied on approval', function () {
    [$team, $manager, $booking] = discountContext('admin');

    $discount = BookingDiscount::factory()->fixed(8000)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts/{$discount->id}/approve");

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'discount_amount' => 8000,
        'total_amount' => 42000,
    ]);
});

test('rejecting a discount leaves the bill at full amount', function () {
    [$team, $manager, $booking] = discountContext('admin');

    $discount = BookingDiscount::factory()->percentage(10)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts/{$discount->id}/reject", [
            'review_notes' => 'Rate too low',
        ])
        ->assertRedirect("/{$team->slug}/bookings");

    expect($discount->fresh()->status)->toBe('rejected');

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'discount_amount' => 0,
        'total_amount' => 50000,
    ]);
});

test('a manager-created discount is auto-approved and applied immediately', function () {
    [$team, $manager, $booking] = discountContext('admin');

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts", [
            'type' => 'percentage',
            'value' => 20,
        ]);

    $this->assertDatabaseHas('booking_discounts', [
        'booking_id' => $booking->id,
        'status' => 'approved',
        'reviewed_by_user_id' => $manager->id,
    ]);

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'discount_amount' => 10000,
        'total_amount' => 40000,
    ]);
});

test('front desk cannot approve a discount', function () {
    [$team, $member, $booking] = discountContext('member');

    $discount = BookingDiscount::factory()->percentage(10)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'status' => 'pending',
    ]);

    $this->actingAs($member)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts/{$discount->id}/approve")
        ->assertForbidden();
});

test('requesting a new discount cancels the prior pending one', function () {
    [$team, $member, $booking] = discountContext('member');

    $first = BookingDiscount::factory()->percentage(5)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'status' => 'pending',
    ]);

    $this->actingAs($member)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts", [
            'type' => 'percentage',
            'value' => 15,
        ]);

    expect($first->fresh()->status)->toBe('cancelled');
    expect($booking->discounts()->where('status', 'pending')->count())->toBe(1);
});

test('an approved percentage discount recomputes after a stay extension', function () {
    [$team, $manager, $booking] = discountContext('admin', 50000);
    $booking->update(['price_per_night' => 10000, 'check_in_date' => now()->toDateString(), 'check_out_date' => now()->addDays(5)->toDateString()]);

    $discount = BookingDiscount::factory()->percentage(10)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'status' => 'pending',
    ]);
    $this->actingAs($manager)->post("/{$team->slug}/bookings/{$booking->id}/discounts/{$discount->id}/approve");

    // Extend the stay: 7 nights * 10000 = 70000 subtotal, 10% => 7000 discount.
    $this->actingAs($manager)->post("/{$team->slug}/bookings/{$booking->id}/extend-stay", [
        'check_out_date' => now()->addDays(7)->toDateString(),
    ]);

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'discount_amount' => 7000,
        'total_amount' => 63000,
    ]);
});

test('creating a booking with a discount records a pending request for front desk', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $member->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id, 'price_per_night' => 10000]);

    $this->actingAs($member)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Grace Hopper',
        'guest_email' => 'grace@example.com',
        'number_of_guests' => 1,
        'check_in_date' => now()->addDay()->toDateString(),
        'check_out_date' => now()->addDays(3)->toDateString(),
        'status' => 'confirmed',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'discount_reason' => 'Loyalty',
    ])->assertRedirect("/{$team->slug}/bookings");

    $booking = Booking::query()->where('guest_name', 'Grace Hopper')->firstOrFail();

    $this->assertDatabaseHas('booking_discounts', [
        'booking_id' => $booking->id,
        'status' => 'pending',
        'type' => 'percentage',
    ]);

    // Bill still full while pending (2 nights * 10000).
    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'discount_amount' => 0,
        'total_amount' => 20000,
    ]);
});
