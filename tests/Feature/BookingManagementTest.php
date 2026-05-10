<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('team members can view the bookings index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $response = $this->actingAs($user)->get("/{$team->slug}/bookings");

    $response->assertStatus(200);
});

test('admins can create bookings', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'guest_phone' => '1234567890',
        'number_of_guests' => 2,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'confirmed',
        'notes' => null,
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertDatabaseHas('bookings', [
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'team_id' => $team->id,
        'room_id' => $room->id,
    ]);
});

test('admins can update bookings', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->patch("/{$team->slug}/bookings/{$booking->id}", [
        'room_id' => $room->id,
        'guest_name' => $booking->guest_name,
        'guest_email' => $booking->guest_email,
        'number_of_guests' => 2,
        'check_in_date' => '2026-07-01',
        'check_out_date' => '2026-07-03',
        'status' => 'confirmed',
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'confirmed',
    ]);
});

test('admins can delete bookings', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create(['team_id' => $team->id, 'room_id' => $room->id]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/bookings/{$booking->id}");

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertModelMissing($booking);
});

test('members can create bookings', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'pending',
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertDatabaseHas('bookings', [
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'team_id' => $team->id,
        'room_id' => $room->id,
    ]);
});

test('members cannot delete bookings', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create(['team_id' => $team->id, 'room_id' => $room->id]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/bookings/{$booking->id}");

    $response->assertStatus(403);
    $this->assertModelExists($booking);
});

test('check_out_date must be after check_in_date', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-06-05',
        'check_out_date' => '2026-06-01',
        'status' => 'pending',
    ]);

    $response->assertSessionHasErrors('check_out_date');
});

test('room must belong to the same team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'pending',
    ]);

    $response->assertSessionHasErrors('room_id');
});

test('cannot create overlapping bookings for the same room', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    // Create first booking
    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'confirmed',
    ]);

    // Try to create overlapping booking
    $response = $this->actingAs($user)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'John Doe',
        'guest_email' => 'john@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-06-03',
        'check_out_date' => '2026-06-07',
        'status' => 'pending',
    ]);

    $response->assertSessionHasErrors('room_id');
});

test('can create non-overlapping bookings for the same room', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    // Create first booking
    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'confirmed',
    ]);

    // Create non-overlapping booking
    $response = $this->actingAs($user)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'John Doe',
        'guest_email' => 'john@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-06-05',
        'check_out_date' => '2026-06-10',
        'status' => 'pending',
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertDatabaseHas('bookings', [
        'guest_name' => 'John Doe',
        'room_id' => $room->id,
    ]);
});

test('room status updates to occupied when booking is checked in', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings", [
        'room_id' => $room->id,
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'number_of_guests' => 1,
        'check_in_date' => '2026-06-01',
        'check_out_date' => '2026-06-05',
        'status' => 'checked_in',
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'status' => 'occupied',
    ]);
});

test('room status updates to available when checked-in booking is checked out', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'status' => 'checked_in',
    ]);

    $response = $this->actingAs($user)->patch("/{$team->slug}/bookings/{$booking->id}", [
        'room_id' => $room->id,
        'guest_name' => $booking->guest_name,
        'guest_email' => $booking->guest_email,
        'number_of_guests' => 1,
        'check_in_date' => $booking->check_in_date->format('Y-m-d'),
        'check_out_date' => $booking->check_out_date->format('Y-m-d'),
        'status' => 'checked_out',
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'status' => 'available',
    ]);
});

test('room status updates to available when booking is deleted', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'status' => 'checked_in',
    ]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/bookings/{$booking->id}");

    $response->assertRedirect("/{$team->slug}/bookings");
    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'status' => 'available',
    ]);
});

test('bookings index can be filtered by payment status and check-in date', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $room = Room::factory()->create(['team_id' => $team->id]);

    $unpaidBooking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-08-01',
        'check_out_date' => '2026-08-03',
    ]);

    $paidBooking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-08-10',
        'check_out_date' => '2026-08-12',
    ]);

    $team->invoices()->create([
        'booking_id' => $unpaidBooking->id,
        'invoice_number' => 'INV-FLT-0001',
        'guest_name' => $unpaidBooking->guest_name,
        'guest_email' => $unpaidBooking->guest_email,
        'issue_date' => '2026-08-01',
        'due_date' => '2026-08-02',
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 100,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $team->invoices()->create([
        'booking_id' => $paidBooking->id,
        'invoice_number' => 'INV-FLT-0002',
        'guest_name' => $paidBooking->guest_name,
        'guest_email' => $paidBooking->guest_email,
        'issue_date' => '2026-08-10',
        'due_date' => '2026-08-11',
        'subtotal' => 200,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 200,
        'paid_amount' => 200,
        'status' => 'paid',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/bookings?payment_status=paid&check_in_from=2026-08-05")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookings.0.id', $paidBooking->id)
            ->where('bookings', fn ($bookings) => count($bookings) === 1));
});
