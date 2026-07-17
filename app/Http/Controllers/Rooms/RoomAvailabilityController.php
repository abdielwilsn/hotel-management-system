<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomAvailabilityController extends Controller
{
    /**
     * List the rooms that are genuinely free for a given date range.
     *
     * The booking wizard calls this whenever the dates change, so staff only
     * ever pick from rooms that will pass validation. Availability is judged by
     * the requested range — not by today's room status — which is what allows an
     * advance booking on a room that happens to be occupied right now.
     */
    public function __invoke(Request $request, Team $current_team): JsonResponse
    {
        Gate::authorize('viewAny', [Room::class, $current_team]);

        $data = $request->validate([
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
        ]);

        $rooms = $current_team->rooms()
            // A room that is out of service can't be sold for any date.
            ->where('status', '!=', 'maintenance')
            ->whereDoesntHave('bookings', function (Builder $query) use ($data): void {
                $query->overlapping($data['check_in_date'], $data['check_out_date']);
            })
            ->orderBy('room_number')
            ->get(['id', 'room_number', 'room_type', 'capacity', 'price_per_night']);

        return response()->json(['rooms' => $rooms]);
    }
}
