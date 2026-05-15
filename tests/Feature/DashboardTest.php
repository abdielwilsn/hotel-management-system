<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get("/{$team->slug}/dashboard");
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get("/{$team->slug}/dashboard");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('metricCards', 4));
});

test('dashboard metric cards are dynamic and team scoped', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $teamRoom = Room::factory()->create(['team_id' => $team->id]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $teamRoom->id,
        'status' => 'checked_in',
        'created_at' => now()->subDay(),
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->toDateString(),
    ]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $teamRoom->id,
        'status' => 'checked_out',
        'created_at' => now()->subDay(),
        'check_in_date' => now()->subDay()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
    ]);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $teamRoom->id,
        'status' => 'confirmed',
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->toDateString(),
    ]);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 120000,
        'issue_date' => now()->toDateString(),
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'status' => 'completed',
        'amount' => 90000,
        'payment_date' => now()->toDateString(),
    ]);

    $otherUser = User::factory()->create();
    $otherTeam = $otherUser->currentTeam;
    $otherRoom = Room::factory()->create(['team_id' => $otherTeam->id]);

    Booking::factory()->create([
        'team_id' => $otherTeam->id,
        'room_id' => $otherRoom->id,
        'status' => 'checked_in',
        'created_at' => now(),
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->toDateString(),
    ]);

    $otherInvoice = Invoice::factory()->create([
        'team_id' => $otherTeam->id,
        'total_amount' => 250000,
        'issue_date' => now()->toDateString(),
    ]);

    Payment::factory()->create([
        'team_id' => $otherTeam->id,
        'invoice_id' => $otherInvoice->id,
        'status' => 'completed',
        'amount' => 180000,
        'payment_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/dashboard")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metricCards.0.title', 'New Bookings')
            ->where('metricCards.0.value', 3)
            ->where('metricCards.1.title', 'Current Check-ins')
            ->where('metricCards.1.value', 2)
            ->where('metricCards.2.title', 'Check-outs Today')
            ->where('metricCards.2.value', 2)
            ->where('metricCards.3.title', 'Revenue (MTD)')
            ->where('metricCards.3.value', 120000));
});
