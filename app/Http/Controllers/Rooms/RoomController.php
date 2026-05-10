<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\SaveRoomRequest;
use App\Models\Room;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Room::class, $current_team]);

        $rooms = $current_team->rooms()
            ->orderBy('room_number')
            ->get();

        $roomTypes = ['single', 'double', 'suite', 'deluxe', 'penthouse'];
        $statuses = ['available', 'occupied', 'maintenance', 'cleaning'];

        return Inertia::render('rooms/Index', [
            'rooms' => $rooms,
            'roomTypes' => $roomTypes,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveRoomRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Room::class, $current_team]);

        $room = $current_team->rooms()->create($request->validated());

        return redirect()->route('rooms.index', $current_team->slug)
            ->with('message', "Room {$room->room_number} has been added.");
    }

    public function edit(Request $request, Team $current_team, Room $room): Response
    {
        $this->roomForTeam($current_team, $room);

        Gate::authorize('update', [$room, $current_team]);

        $roomTypes = ['single', 'double', 'suite', 'deluxe', 'penthouse'];
        $statuses = ['available', 'occupied', 'maintenance', 'cleaning'];

        return Inertia::render('rooms/Edit', [
            'room' => $room,
            'roomTypes' => $roomTypes,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SaveRoomRequest $request, Team $current_team, Room $room): RedirectResponse
    {
        $this->roomForTeam($current_team, $room);

        Gate::authorize('update', [$room, $current_team]);

        $room->update($request->validated());

        return redirect()->route('rooms.index', $current_team->slug)
            ->with('message', "Room {$room->room_number} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, Room $room): RedirectResponse
    {
        $this->roomForTeam($current_team, $room);

        Gate::authorize('delete', [$room, $current_team]);

        $roomNumber = $room->room_number;
        $room->delete();

        return redirect()->route('rooms.index', $current_team->slug)
            ->with('message', "Room {$roomNumber} has been removed.");
    }

    private function roomForTeam(Team $team, Room $room): void
    {
        if ($room->team_id !== $team->id) {
            abort(403);
        }
    }
}
