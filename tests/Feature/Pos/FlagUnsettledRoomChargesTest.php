<?php

use App\Models\Booking;
use App\Models\PosMenuItem;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Bookings\BookingNeedsSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a booking checking out today with an unsettled room charge is flagged once', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_out_date' => now()->toDateString(),
        'status' => 'checked_in',
        'total_amount' => 0,
    ]);

    $item = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id, 'price' => 400, 'track_stock' => false]);

    $this->actingAs($manager)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'room',
        'payment_mode' => 'room',
        'booking_id' => $booking->id,
        'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 1]],
    ]);

    $order = PosOrder::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($order->status)->toBe('pending');

    $this->artisan('pos:flag-unsettled-room-charges')->assertSuccessful();

    Notification::assertSentTo(
        $manager,
        fn (BookingNeedsSettlement $notification) => $notification->booking->is($booking->fresh()),
    );
    expect($booking->fresh()->notified_needs_settlement_at)->not->toBeNull();

    Notification::fake();
    $this->artisan('pos:flag-unsettled-room-charges')->assertSuccessful();
    Notification::assertNothingSent();
});

test('a booking with no pending room charges is not flagged', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $team->id]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_out_date' => now()->toDateString(),
        'status' => 'checked_in',
    ]);

    $this->artisan('pos:flag-unsettled-room-charges')->assertSuccessful();

    Notification::assertNothingSent();
});
