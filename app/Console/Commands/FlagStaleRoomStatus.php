<?php

namespace App\Console\Commands;

use App\Enums\Ability;
use App\Models\Room;
use App\Notifications\Rooms\RoomStatusStale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * A room left in maintenance or cleaning for too long is either forgotten
 * about or a turnover risk for the next guest — either way, somebody should
 * look at it rather than find out when a booking can't be assigned a room.
 */
class FlagStaleRoomStatus extends Command
{
    protected $signature = 'rooms:flag-stale-status';

    protected $description = 'Alert managers to rooms stuck in maintenance or cleaning';

    /**
     * Cleaning is meant to be quick between guests; maintenance can
     * legitimately take a day. Different status, different patience.
     */
    private const THRESHOLD_HOURS = [
        'cleaning' => 4,
        'maintenance' => 24,
    ];

    public function handle(): int
    {
        $flagged = 0;

        foreach (self::THRESHOLD_HOURS as $status => $hours) {
            $rooms = Room::query()
                ->where('status', $status)
                ->where('updated_at', '<=', now()->subHours($hours))
                ->where(function ($query): void {
                    // Never alerted, or the status has changed (and gone
                    // stale again) since the last alert.
                    $query->whereNull('status_alerted_at')
                        ->orWhereColumn('status_alerted_at', '<', 'updated_at');
                })
                ->with('team')
                ->get();

            foreach ($rooms as $room) {
                if ($room->team === null) {
                    continue;
                }

                Notification::send(
                    $room->team->membersWithAbility(Ability::ManageRooms),
                    new RoomStatusStale($room, $hours),
                );

                // Query builder update — bypasses fillable and, crucially,
                // doesn't touch updated_at, which is the very clock we're
                // measuring staleness against.
                Room::query()->whereKey($room->id)->update([
                    'status_alerted_at' => now(),
                ]);

                $flagged++;
            }
        }

        $this->info($flagged > 0
            ? "Flagged {$flagged} room(s) stuck in maintenance or cleaning."
            : 'No rooms have been stuck in maintenance or cleaning for too long.');

        return self::SUCCESS;
    }
}
