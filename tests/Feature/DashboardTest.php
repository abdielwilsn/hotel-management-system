<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Request the dashboard the way Inertia's client fetches a deferred prop:
 * a partial reload scoped to `metricCards`.
 */
function loadDashboardMetrics(string $slug): TestResponse
{
    $version = app(HandleInertiaRequests::class)
        ->version(request());

    return test()->get("/{$slug}/dashboard", array_filter([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'metricCards',
    ]));
}

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get("/{$team->slug}/dashboard");
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->get("/{$team->slug}/dashboard")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    // metricCards is deferred; it arrives on the follow-up partial reload (JSON).
    loadDashboardMetrics($team->slug)
        ->assertOk()
        ->assertJsonCount(4, 'props.metricCards');
});

test('dashboard metric cards are dynamic and team scoped', function () {
    // "New Bookings" counts the current calendar week, and this test places
    // bookings a day back. Freeze to midweek so the run doesn't break every
    // Monday, when yesterday belongs to the previous week.
    $this->travelTo(Carbon::parse('2026-06-17 09:00:00')); // Wednesday

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

    $this->actingAs($user);

    loadDashboardMetrics($team->slug)
        ->assertOk()
        ->assertJsonPath('props.metricCards.0.title', 'New Bookings')
        ->assertJsonPath('props.metricCards.0.value', 3)
        ->assertJsonPath('props.metricCards.1.title', 'Current Check-ins')
        ->assertJsonPath('props.metricCards.1.value', 2)
        ->assertJsonPath('props.metricCards.2.title', 'Check-outs Today')
        ->assertJsonPath('props.metricCards.2.value', 2)
        ->assertJsonPath('props.metricCards.3.title', 'Revenue (MTD)')
        ->assertJsonPath('props.metricCards.3.value', 120000);
});
