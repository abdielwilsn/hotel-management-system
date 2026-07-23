<?php

use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Bookings\StayAdjustmentRequested;
use App\Notifications\Bookings\StayAdjustmentReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function stayAdjustmentHotel(float $rate = 10000): array
{
    $team = Team::factory()->create([
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
        'early_check_in_from' => '08:00',
    ]);

    $room = Room::factory()->for($team)->create(['price_per_night' => $rate]);

    return [$team, $room];
}

function stayAdjustmentBooking(Team $team, Room $room, string $arrival, string $departure)
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

test('requesting a night-count change notifies reviewers but not the requester', function () {
    Notification::fake();

    [$team, $room] = stayAdjustmentHotel();
    $booking = stayAdjustmentBooking($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');

    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($frontDesk)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
            'requested_nights' => 1,
        ])
        ->assertRedirect();

    Notification::assertSentTo($manager, StayAdjustmentRequested::class);
    Notification::assertNotSentTo($frontDesk, StayAdjustmentRequested::class);
});

test('a manager requesting their own night-count change is auto-applied and notifies nobody', function () {
    Notification::fake();

    [$team, $room] = stayAdjustmentHotel();
    $booking = stayAdjustmentBooking($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');

    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
            'requested_nights' => 1,
        ]);

    Notification::assertNothingSent();
});

test('approving a night-count change notifies the person who requested it', function () {
    Notification::fake();

    [$team, $room] = stayAdjustmentHotel();
    $booking = stayAdjustmentBooking($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');

    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($frontDesk)->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
        'requested_nights' => 1,
    ]);

    $adjustment = $booking->fresh()->pendingStayAdjustment();

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments/{$adjustment->id}/approve")
        ->assertRedirect();

    Notification::assertSentTo(
        $frontDesk,
        fn (StayAdjustmentReviewed $notification) => $notification->adjustment->status === 'approved',
    );
    Notification::assertNotSentTo($manager, StayAdjustmentReviewed::class);
});

test('rejecting a night-count change notifies the person who requested it', function () {
    Notification::fake();

    [$team, $room] = stayAdjustmentHotel();
    $booking = stayAdjustmentBooking($team, $room, '2026-03-10 08:00', '2026-03-11 12:00');

    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($frontDesk)->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments", [
        'requested_nights' => 1,
    ]);

    $adjustment = $booking->fresh()->pendingStayAdjustment();

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/stay-adjustments/{$adjustment->id}/reject")
        ->assertRedirect();

    Notification::assertSentTo(
        $frontDesk,
        fn (StayAdjustmentReviewed $notification) => $notification->adjustment->status === 'rejected',
    );
});
