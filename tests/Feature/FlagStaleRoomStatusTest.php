<?php

use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Rooms\RoomStatusStale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a room stuck in cleaning past the threshold is flagged once', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'cleaning']);
    Room::query()->whereKey($room->id)->update(['updated_at' => now()->subHours(5)]);

    $this->artisan('rooms:flag-stale-status')->assertSuccessful();

    Notification::assertSentTo(
        $manager,
        fn (RoomStatusStale $notification) => $notification->room->is($room) && $notification->hoursStale === 4,
    );
    expect($room->fresh()->status_alerted_at)->not->toBeNull();

    Notification::fake();
    $this->artisan('rooms:flag-stale-status')->assertSuccessful();
    Notification::assertNothingSent();
});

test('a room in cleaning for under the threshold is not flagged', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'cleaning']);
    Room::query()->whereKey($room->id)->update(['updated_at' => now()->subHours(1)]);

    $this->artisan('rooms:flag-stale-status')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a room re-flags once it goes stale again after a later status change', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $room = Room::factory()->create(['team_id' => $team->id, 'status' => 'maintenance']);
    Room::query()->whereKey($room->id)->update(['updated_at' => now()->subHours(25)]);

    $this->artisan('rooms:flag-stale-status')->assertSuccessful();
    Notification::assertSentTo($manager, RoomStatusStale::class);

    // The alert was a while ago; the room went back into service and later
    // broke again more recently than that — updated_at moving past the old
    // status_alerted_at is what should re-arm the alert.
    Room::query()->whereKey($room->id)->update(['status_alerted_at' => now()->subHours(48)]);
    Room::query()->whereKey($room->id)->update([
        'status' => 'maintenance',
        'updated_at' => now()->subHours(25),
    ]);

    Notification::fake();
    $this->artisan('rooms:flag-stale-status')->assertSuccessful();
    Notification::assertSentTo($manager, RoomStatusStale::class);
});
