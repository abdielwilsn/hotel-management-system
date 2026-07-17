<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\SaveRoomTypeRequest;
use App\Models\RoomType;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Managers curate the room types their hotel sells. Rooms reference a type by
 * slug, so renaming a type is safe while the slug stays put.
 */
class RoomTypeController extends Controller
{
    public function store(SaveRoomTypeRequest $request, Team $current_team): RedirectResponse
    {
        $name = (string) $request->validated('name');

        $current_team->roomTypes()->create([
            'name' => $name,
            'slug' => RoomType::uniqueSlugFor($current_team, $name),
        ]);

        return back()->with('success', "Room type \"{$name}\" added.");
    }

    public function update(SaveRoomTypeRequest $request, Team $current_team, RoomType $room_type): RedirectResponse
    {
        $this->ensureBelongsToTeam($current_team, $room_type);

        // Only the display name changes; the slug stays so existing rooms keep
        // pointing at this type.
        $room_type->update(['name' => (string) $request->validated('name')]);

        return back()->with('success', 'Room type renamed.');
    }

    public function destroy(Team $current_team, RoomType $room_type): RedirectResponse
    {
        $this->ensureBelongsToTeam($current_team, $room_type);

        $roomsUsing = $current_team->rooms()->where('room_type', $room_type->slug)->count();

        if ($roomsUsing > 0) {
            throw ValidationException::withMessages([
                'room_type' => "\"{$room_type->name}\" is still used by {$roomsUsing} room(s). Move those rooms to another type first.",
            ]);
        }

        if ($current_team->roomTypes()->count() <= 1) {
            throw ValidationException::withMessages([
                'room_type' => 'You need at least one room type.',
            ]);
        }

        $room_type->delete();

        return back()->with('success', 'Room type removed.');
    }

    private function ensureBelongsToTeam(Team $team, RoomType $roomType): void
    {
        abort_unless($roomType->team_id === $team->id, 404);
    }
}
