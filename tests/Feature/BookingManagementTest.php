<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosOutlet;
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
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
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
        'updated_by_user_id' => $user->id,
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
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
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

test('booking form only lists currently available rooms', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $availableRoom = Room::factory()->create([
        'team_id' => $team->id,
        'status' => 'available',
        'room_number' => '101',
    ]);

    $reservedRoom = Room::factory()->create([
        'team_id' => $team->id,
        'status' => 'available',
        'room_number' => '102',
    ]);

    $occupiedRoom = Room::factory()->create([
        'team_id' => $team->id,
        'status' => 'available',
        'room_number' => '103',
    ]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $reservedRoom->id,
        'status' => 'confirmed',
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
    ]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $occupiedRoom->id,
        'status' => 'checked_in',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rooms', fn ($rooms) => count($rooms) === 1)
            ->where('rooms.0.id', $availableRoom->id)
            ->where('rooms.0.room_number', '101'));
});

test('booking payments can be recorded as partial payments', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 300,
        'status' => 'confirmed',
    ]);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'total_amount' => 300,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/process-payment", [
        'amount' => 120,
        'method' => 'cash',
        'payment_date' => '2026-07-10',
        'status' => 'completed',
    ]);

    $payment = Payment::query()
        ->where('team_id', $team->id)
        ->where('invoice_id', $invoice->id)
        ->where('amount', 120)
        ->first();

    expect($payment)->not->toBeNull();

    $response->assertRedirect("/{$team->slug}/payments/{$payment->id}/receipt");
    $this->assertDatabaseHas('payments', [
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 120,
        'status' => 'completed',
    ]);

    expect((float) $invoice->fresh()->paid_amount)->toBe(120.0);
    expect($invoice->fresh()->status)->toBe('partially_paid');
});

test('checking out a booking can settle the remaining balance and release the room', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 400,
        'status' => 'checked_in',
    ]);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'total_amount' => 400,
        'paid_amount' => 150,
        'status' => 'partially_paid',
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 150,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/checkout", [
        'settlement_amount' => 250,
        'settlement_method' => 'card',
        'settlement_payment_date' => '2026-07-11',
        'settlement_reference' => 'CHK-250',
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");
    expect($booking->fresh()->status)->toBe('checked_out');
    expect((float) $invoice->fresh()->paid_amount)->toBe(400.0);
    expect($invoice->fresh()->status)->toBe('paid');

    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'status' => 'available',
    ]);
});

test('checkout requires the full remaining balance when money is still owed', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'total_amount' => 400,
        'status' => 'checked_in',
    ]);

    Invoice::factory()->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'total_amount' => 400,
        'paid_amount' => 150,
        'status' => 'partially_paid',
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/checkout", [
        'settlement_amount' => 200,
        'settlement_method' => 'cash',
        'settlement_payment_date' => '2026-07-11',
    ]);

    $response->assertSessionHasErrors('settlement_amount');
});

test('extending a stay updates the booking total and invoice balance', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create([
        'team_id' => $team->id,
        'price_per_night' => 100,
    ]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-08-01',
        'check_out_date' => '2026-08-03',
        'price_per_night' => 100,
        'total_amount' => 200,
        'status' => 'checked_in',
        'notes' => 'Initial stay',
    ]);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'total_amount' => 200,
        'paid_amount' => 100,
        'status' => 'partially_paid',
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/bookings/{$booking->id}/extend-stay", [
        'check_out_date' => '2026-08-05',
        'notes' => 'Guest requested two extra nights',
    ]);

    $response->assertRedirect("/{$team->slug}/bookings");

    expect($booking->fresh()->check_out_date->format('Y-m-d'))->toBe('2026-08-05');
    expect((float) $booking->fresh()->total_amount)->toBe(400.0);
    expect((float) $invoice->fresh()->total_amount)->toBe(400.0);
    expect((float) $invoice->fresh()->paid_amount)->toBe(100.0);
    expect($invoice->fresh()->status)->toBe('partially_paid');
    expect($booking->fresh()->notes)->toContain('Guest requested two extra nights');
});

test('bookings index includes folio payment lines and extension history', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'status' => 'checked_in',
        'notes' => "Guest requested a baby cot\nExtension: Added one extra night",
    ]);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'invoice_number' => 'INV-FOLIO-1001',
        'issue_date' => '2026-09-01',
        'due_date' => '2026-09-03',
        'total_amount' => 250,
        'paid_amount' => 125,
        'status' => 'partially_paid',
    ]);

    $payment = Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-FOLIO-1001',
        'payment_date' => '2026-09-01',
        'amount' => 125,
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookings.0.id', $booking->id)
            ->where('bookings.0.invoice.invoice_number', 'INV-FOLIO-1001')
            ->where('bookings.0.invoice.payments.0.payment_number', 'PAY-FOLIO-1001')
            // The payment id is what the folio's "Reprint" link is built from,
            // so it must survive the eager-load select.
            ->where('bookings.0.invoice.payments.0.id', $payment->id)
            ->where('bookings.0.extension_history.0.label', 'Added one extra night'));
});

test('a payment made against a booking can be reprinted from its receipt route', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'status' => 'checked_in',
    ]);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'total_amount' => 300,
        'paid_amount' => 300,
    ]);

    // Several payments can be made against the same booking's invoice; each
    // one keeps its own reprintable receipt.
    $firstPayment = Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-REPRINT-1001',
        'payment_date' => '2026-09-01',
        'amount' => 100,
        'status' => 'completed',
    ]);

    $secondPayment = Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-REPRINT-1002',
        'payment_date' => '2026-09-02',
        'amount' => 200,
        'status' => 'completed',
    ]);

    $bookingResponse = $this->actingAs($user)->get("/{$team->slug}/bookings");
    $bookingResponse->assertInertia(fn (Assert $page) => $page
        ->where('bookings.0.invoice.payments.0.payment_number', 'PAY-REPRINT-1002')
        ->where('bookings.0.invoice.payments.1.payment_number', 'PAY-REPRINT-1001'));

    foreach ([$firstPayment, $secondPayment] as $payment) {
        $this->actingAs($user)
            ->get("/{$team->slug}/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('payments/Receipt')
                ->where('payment.id', $payment->id)
                ->where('payment.payment_number', $payment->payment_number));
    }
});

test('the bookings list shows the newest booking first', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);

    // Created first, but has the furthest-future stay — it must NOT lead.
    $oldest = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2027-01-01',
        'check_out_date' => '2027-01-05',
        'created_at' => now()->subDay(),
    ]);

    $newest = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'check_in_date' => '2026-01-01',
        'check_out_date' => '2026-01-05',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookings.0.id', $newest->id)
            ->where('bookings.1.id', $oldest->id));
});

test('bookings index includes the outlet and items for a room-charge POS order', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id, 'name' => 'Poolside Bar']);

    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'status' => 'checked_in',
    ]);

    $order = PosOrder::factory()->create([
        'team_id' => $team->id,
        'pos_outlet_id' => $outlet->id,
        'booking_id' => $booking->id,
        'charge_type' => 'room',
        'status' => 'pending',
        'order_number' => 'BAR-ROOM-0001',
        'total' => 900,
    ]);

    $order->items()->create([
        'name' => 'Club Sandwich',
        'unit_price' => 450,
        'quantity' => 2,
        'line_total' => 900,
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookings.0.pos_orders.0.order_number', 'BAR-ROOM-0001')
            ->where('bookings.0.pos_orders.0.status', 'pending')
            ->where('bookings.0.pos_orders.0.total', '900.00')
            ->where('bookings.0.pos_orders.0.outlet.name', 'Poolside Bar')
            ->where('bookings.0.pos_orders.0.items.0.name', 'Club Sandwich')
            ->where('bookings.0.pos_orders.0.items.0.quantity', 2)
            ->where('bookings.0.pos_orders.0.items.0.line_total', '900.00'));
});

test('a room-charge order for a different booking does not leak into this booking\'s folio', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id]);

    $thisBooking = Booking::factory()->create(['team_id' => $team->id, 'room_id' => $room->id]);
    $otherBooking = Booking::factory()->create(['team_id' => $team->id, 'room_id' => $room->id]);

    PosOrder::factory()->create([
        'team_id' => $team->id,
        'pos_outlet_id' => $outlet->id,
        'booking_id' => $thisBooking->id,
        'charge_type' => 'room',
        'order_number' => 'BAR-ROOM-0002',
    ]);

    PosOrder::factory()->create([
        'team_id' => $team->id,
        'pos_outlet_id' => $outlet->id,
        'booking_id' => $otherBooking->id,
        'charge_type' => 'room',
        'order_number' => 'BAR-ROOM-0003',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/bookings")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookings.0.id', $otherBooking->id)
            ->where('bookings.0.pos_orders.0.order_number', 'BAR-ROOM-0003')
            ->where('bookings.0.pos_orders', fn ($orders) => count($orders) === 1)
            ->where('bookings.1.id', $thisBooking->id)
            ->where('bookings.1.pos_orders.0.order_number', 'BAR-ROOM-0002')
            ->where('bookings.1.pos_orders', fn ($orders) => count($orders) === 1));
});
