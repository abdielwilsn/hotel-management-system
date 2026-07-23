<?php

namespace App\Notifications\Rooms;

use App\Models\Room;
use App\Notifications\TeamNotification;

class RoomStatusStale extends TeamNotification
{
    public function __construct(public Room $room, public int $hoursStale)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, room_id: int, status: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->room->team_id,
            'message' => "Room {$this->room->room_number} has been in {$this->room->status} for {$this->hoursStale}+ hours.",
            'url' => route('rooms.index', $this->room->team->slug),
            'room_id' => $this->room->id,
            'status' => $this->room->status,
        ];
    }
}
