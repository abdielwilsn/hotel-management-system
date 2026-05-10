<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('team members can view the invoices index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $response = $this->actingAs($user)->get("/{$team->slug}/invoices");

    $response->assertStatus(200);
});

test('admins can create invoices and total is computed', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $room = Room::factory()->create(['team_id' => $team->id]);
    $booking = Booking::factory()->create(['team_id' => $team->id, 'room_id' => $room->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/invoices", [
        'booking_id' => $booking->id,
        'invoice_number' => 'INV-2026-1001',
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-05',
        'subtotal' => 500,
        'tax_amount' => 50,
        'discount_amount' => 20,
        'paid_amount' => 100,
        'status' => 'issued',
    ]);

    $response->assertRedirect("/{$team->slug}/invoices");
    $this->assertDatabaseHas('invoices', [
        'team_id' => $team->id,
        'booking_id' => $booking->id,
        'invoice_number' => 'INV-2026-1001',
        'total_amount' => 530,
    ]);
});

test('admins can update invoices', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-2026-1002',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user)->patch("/{$team->slug}/invoices/{$invoice->id}", [
        'booking_id' => null,
        'invoice_number' => 'INV-2026-1002',
        'guest_name' => 'Updated Guest',
        'guest_email' => 'updated@example.com',
        'issue_date' => '2026-07-10',
        'due_date' => '2026-07-15',
        'subtotal' => 700,
        'tax_amount' => 70,
        'discount_amount' => 10,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $response->assertRedirect("/{$team->slug}/invoices");
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'guest_name' => 'Updated Guest',
        'total_amount' => 760,
        'status' => 'issued',
    ]);
});

test('admins can delete invoices', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $invoice = Invoice::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/invoices/{$invoice->id}");

    $response->assertRedirect("/{$team->slug}/invoices");
    $this->assertModelMissing($invoice);
});

test('members cannot create invoices', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $response = $this->actingAs($user)->post("/{$team->slug}/invoices", [
        'booking_id' => null,
        'invoice_number' => 'INV-2026-1100',
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-05',
        'subtotal' => 500,
        'tax_amount' => 50,
        'discount_amount' => 20,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $response->assertStatus(403);
});

test('booking must belong to the same team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $otherRoom = Room::factory()->create(['team_id' => $otherTeam->id]);
    $booking = Booking::factory()->create(['team_id' => $otherTeam->id, 'room_id' => $otherRoom->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/invoices", [
        'booking_id' => $booking->id,
        'invoice_number' => 'INV-2026-1200',
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-05',
        'subtotal' => 500,
        'tax_amount' => 50,
        'discount_amount' => 20,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $response->assertSessionHasErrors('booking_id');
});

test('paid amount cannot exceed computed total', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $response = $this->actingAs($user)->post("/{$team->slug}/invoices", [
        'booking_id' => null,
        'invoice_number' => 'INV-2026-1300',
        'guest_name' => 'Jane Smith',
        'guest_email' => 'jane@example.com',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-05',
        'subtotal' => 500,
        'tax_amount' => 50,
        'discount_amount' => 20,
        'paid_amount' => 1000,
        'status' => 'issued',
    ]);

    $response->assertSessionHasErrors('paid_amount');
});
