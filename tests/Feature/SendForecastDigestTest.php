<?php

use App\Models\Booking;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Forecasts\ForecastDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a team with forecast risk alerts gets a digest sent to whoever can view forecasts', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);
    $frontDesk = User::factory()->create();
    $frontDesk->teams()->attach($team, ['role' => 'member']);

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

    $this->artisan('forecasts:daily-digest')->assertSuccessful();

    Notification::assertSentTo(
        $manager,
        fn (ForecastDigest $notification) => count($notification->alerts) === 3,
    );
    Notification::assertNotSentTo($frontDesk, ForecastDigest::class);
});

test('a team with no forecast risk is not sent a digest', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    // A fully booked, fully paid, expense-free team has nothing to flag.
    for ($i = 0; $i < 3; $i++) {
        $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'occupied']);
        Booking::factory()->create([
            'team_id' => $team->id,
            'room_id' => $room->id,
            'status' => 'confirmed',
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'total_amount' => 400,
        ]);
    }

    $this->artisan('forecasts:daily-digest')->assertSuccessful();

    Notification::assertNothingSent();
});
