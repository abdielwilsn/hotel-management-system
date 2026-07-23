<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\PosMenuItem;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function chargeRoomOrder(Team $team, User $user, PosOutlet $outlet, Booking $booking, float $price = 300): void
{
    $item = PosMenuItem::factory()->create([
        'team_id' => $team->id,
        'pos_outlet_id' => $outlet->id,
        'price' => $price,
        'track_stock' => false,
    ]);

    test()->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'room',
        'payment_mode' => 'room',
        'booking_id' => $booking->id,
        'items' => [
            ['pos_menu_item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect();
}

test('a room charge survives a subsequent stay extension', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);
    $room = Room::factory()->create(['team_id' => $team->id, 'price_per_night' => 100]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-08-01',
        'check_out_date' => '2026-08-03',
        'price_per_night' => 100,
        'total_amount' => 200,
        'status' => 'checked_in',
    ]);

    chargeRoomOrder($team, $user, $outlet, $booking, price: 150);

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'total_amount' => 350, // 200 room + 150 bar tab
    ]);

    $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/extend-stay", [
        'check_out_date' => '2026-08-05',
    ])->assertRedirect("/{$team->slug}/bookings");

    // Extending re-syncs the invoice from the room total alone — the POS
    // charge must still be there afterwards, not wiped out.
    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'total_amount' => 550, // 400 room (4 nights) + 150 bar tab
    ]);
});

test('a room charge survives a booking update', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);
    $room = Room::factory()->create(['team_id' => $team->id, 'price_per_night' => 100]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-08-01',
        'check_out_date' => '2026-08-03',
        'price_per_night' => 100,
        'total_amount' => 200,
        'status' => 'pending',
    ]);

    chargeRoomOrder($team, $user, $outlet, $booking, price: 150);

    $this->actingAs($user)->patch("/{$team->slug}/bookings/{$booking->id}", [
        'room_id' => $room->id,
        'guest_name' => $booking->guest_name,
        'guest_email' => $booking->guest_email,
        'number_of_guests' => 2,
        'check_in_date' => '2026-08-01',
        'check_out_date' => '2026-08-03',
        'status' => 'confirmed',
    ])->assertRedirect("/{$team->slug}/bookings");

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'total_amount' => 350,
    ]);
});

test('checkout is blocked while a POS room charge remains unsettled', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 0,
        'status' => 'checked_in',
    ]);

    chargeRoomOrder($team, $user, $outlet, $booking, price: 300);

    // Trying to check out without settling the bar tab is refused.
    $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/checkout", [])
        ->assertSessionHasErrors('settlement_amount');

    expect($booking->fresh()->status)->toBe('checked_in');

    // Settling the full outstanding balance lets checkout proceed.
    $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/checkout", [
        'settlement_amount' => 300,
        'settlement_method' => 'cash',
        'settlement_payment_date' => '2026-07-11',
    ])->assertRedirect("/{$team->slug}/bookings");

    expect($booking->fresh()->status)->toBe('checked_out');
});

test('a room-charge order flips to paid once the invoice is fully settled', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 0,
        'status' => 'checked_in',
    ]);

    chargeRoomOrder($team, $user, $outlet, $booking, price: 300);
    $order = PosOrder::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($order->status)->toBe('pending');

    $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/checkout", [
        'settlement_amount' => 300,
        'settlement_method' => 'cash',
        'settlement_payment_date' => '2026-07-11',
    ])->assertRedirect("/{$team->slug}/bookings");

    $order->refresh();
    expect($order->status)->toBe('paid');
    expect($order->paid_at)->not->toBeNull();
});

test('a room-charge order stays pending while the invoice is only partially paid', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 0,
        'status' => 'checked_in',
    ]);

    chargeRoomOrder($team, $user, $outlet, $booking, price: 300);
    $order = PosOrder::query()->where('booking_id', $booking->id)->firstOrFail();

    $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/process-payment", [
        'amount' => 100,
        'method' => 'cash',
        'payment_date' => '2026-07-10',
        'status' => 'completed',
    ]);

    $order->refresh();
    expect($order->status)->toBe('pending');
    expect($order->paid_at)->toBeNull();
});

test('a payment recorded via the standalone Payments module also settles a pending room charge', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 0,
        'status' => 'checked_in',
    ]);

    chargeRoomOrder($team, $user, $outlet, $booking, price: 300);
    $order = PosOrder::query()->where('booking_id', $booking->id)->firstOrFail();
    $invoice = Invoice::query()->where('booking_id', $booking->id)->firstOrFail();

    $this->actingAs($user)->post("/{$team->slug}/payments", [
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-2026-9001',
        'payment_date' => '2026-07-10',
        'amount' => 300,
        'method' => 'cash',
        'status' => 'completed',
    ])->assertRedirect("/{$team->slug}/payments");

    expect($invoice->fresh()->status)->toBe('paid');

    $order->refresh();
    expect($order->status)->toBe('paid');
    expect($order->paid_at)->not->toBeNull();
});
