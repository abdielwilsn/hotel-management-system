<?php

use App\Models\Booking;
use App\Models\BookingDiscount;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Bookings\DiscountRequested;
use App\Notifications\Bookings\DiscountReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a pending discount request notifies reviewers but not a plain member', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);
    $bystander = User::factory()->create();
    $bystander->teams()->attach($team, ['role' => 'member']);

    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 50000,
        'status' => 'confirmed',
    ]);

    $this->actingAs($frontDesk)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts", [
            'type' => 'percentage',
            'value' => 10,
        ]);

    Notification::assertSentTo($manager, DiscountRequested::class);
    Notification::assertNotSentTo($bystander, DiscountRequested::class);
    Notification::assertNotSentTo($frontDesk, DiscountRequested::class);
});

test('a manager auto-approved discount does not notify anyone as a pending request', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 50000,
        'status' => 'confirmed',
    ]);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts", [
            'type' => 'percentage',
            'value' => 10,
        ]);

    Notification::assertNothingSent();
});

test('approving a discount notifies the person who requested it, not the approver', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 50000,
        'status' => 'confirmed',
    ]);

    $discount = BookingDiscount::factory()->percentage(10)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'requested_by_user_id' => $frontDesk->id,
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts/{$discount->id}/approve");

    Notification::assertSentTo(
        $frontDesk,
        fn (DiscountReviewed $notification) => $notification->discount->status === 'approved',
    );
    Notification::assertNotSentTo($manager, DiscountReviewed::class);
});

test('rejecting a discount notifies the person who requested it', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 50000,
        'status' => 'confirmed',
    ]);

    $discount = BookingDiscount::factory()->percentage(10)->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'requested_by_user_id' => $frontDesk->id,
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->post("/{$team->slug}/bookings/{$booking->id}/discounts/{$discount->id}/reject", [
            'review_notes' => 'Rate too low',
        ]);

    Notification::assertSentTo(
        $frontDesk,
        fn (DiscountReviewed $notification) => $notification->discount->status === 'rejected',
    );
});
