<?php

use App\Models\Booking;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('team members can view forecasting page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/forecasts")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('forecasts/Index')
            ->has('forecast')
            ->has('alerts'));
});

test('forecasting includes occupancy and revenue projections', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $roomA = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);
    Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $roomA->id,
        'status' => 'confirmed',
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 600,
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/forecasts")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('forecast.total_rooms', 2)
            ->where('forecast.upcoming_bookings_30_days', 1));
});

test('forecast alerts include profitability and collection risks when data indicates it', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);
    Room::factory()->create(['team_id' => $team->id, 'status' => 'available']);

    Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 1000,
        'paid_amount' => 100,
        'status' => 'issued',
    ]);

    Expense::factory()->create([
        'team_id' => $team->id,
        'status' => 'paid',
        'amount' => 2000,
        'incurred_date' => now()->subDays(2)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/forecasts")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts', 3)
            ->where('alerts.0.title', 'Occupancy risk'));
});

test('forecast values only include current team data', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    Room::factory()->count(2)->create(['team_id' => $team->id, 'status' => 'available']);
    Room::factory()->create(['team_id' => $otherTeam->id, 'status' => 'occupied']);

    Booking::factory()->create([
        'team_id' => $otherTeam->id,
        'room_id' => Room::factory()->create(['team_id' => $otherTeam->id])->id,
        'status' => 'confirmed',
        'check_in_date' => now()->addDays(2)->toDateString(),
        'check_out_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/forecasts")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('forecast.total_rooms', 2)
            ->where('forecast.upcoming_bookings_30_days', 0));

});
