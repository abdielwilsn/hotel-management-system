<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('managers can view reports index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->get("/{$team->slug}/reports")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->has('summary')
            ->has('monthlyTrend')
            ->has('paymentMethods'));
});

test('receptionists cannot view reports index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/reports")
        ->assertForbidden();
});

test('reports summary values are calculated from team data', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);
    Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);
    $bookingRoom = Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $bookingRoom->id,
        'status' => 'pending',
        'check_in_date' => now()->addDay()->toDateString(),
        'check_out_date' => now()->addDays(3)->toDateString(),
        'price_per_night' => 120,
    ]);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 300,
        'status' => 'issued',
        'issue_date' => now()->toDateString(),
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 200,
        'status' => 'completed',
        'payment_date' => now()->toDateString(),
        'method' => 'card',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/reports")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_rooms', 3)
            ->where('summary.occupied_rooms', 1)
            ->where('summary.occupancy_rate', 33.3)
            ->where('summary.gross_revenue', 300)
            ->where('summary.collected_revenue', 200)
            ->where('summary.outstanding_revenue', 100));
});

test('reports only include current team data', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $teamInvoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 400,
        'status' => 'issued',
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $teamInvoice->id,
        'amount' => 150,
        'status' => 'completed',
        'method' => 'cash',
    ]);

    $otherInvoice = Invoice::factory()->create([
        'team_id' => $otherTeam->id,
        'total_amount' => 1000,
        'status' => 'issued',
    ]);

    Payment::factory()->create([
        'team_id' => $otherTeam->id,
        'invoice_id' => $otherInvoice->id,
        'amount' => 1000,
        'status' => 'completed',
        'method' => 'online',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/reports")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.gross_revenue', 400)
            ->where('summary.collected_revenue', 150));
});
