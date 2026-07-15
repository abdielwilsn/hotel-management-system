<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController extends Controller
{
    /**
     * Lightweight global search across guests, bookings, and rooms for the
     * command palette. Returns grouped results that link to the relevant
     * module index, pre-filtered by the search term.
     */
    public function index(Request $request, Team $current_team): JsonResponse
    {
        Gate::authorize('viewAny', [Booking::class, $current_team]);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$term.'%';
        $results = [];

        $guests = Guest::query()
            ->where('team_id', $current_team->id)
            ->where(function (Builder $query) use ($like): void {
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('last_name')
            ->limit(5)
            ->get();

        foreach ($guests as $guest) {
            $results[] = [
                'type' => 'Guest',
                'label' => $guest->full_name,
                'sublabel' => $guest->email ?: $guest->phone,
                'href' => route('guests.index', ['current_team' => $current_team->slug, 'search' => $guest->full_name]),
            ];
        }

        $bookings = Booking::query()
            ->where('team_id', $current_team->id)
            ->with('room:id,room_number')
            ->where(function (Builder $query) use ($like): void {
                $query->where('guest_name', 'like', $like)
                    ->orWhere('guest_email', 'like', $like)
                    ->orWhere('guest_phone', 'like', $like)
                    ->orWhereHas('room', function (Builder $roomQuery) use ($like): void {
                        $roomQuery->where('room_number', 'like', $like);
                    });
            })
            ->orderByDesc('check_in_date')
            ->limit(5)
            ->get();

        foreach ($bookings as $booking) {
            $results[] = [
                'type' => 'Booking',
                'label' => $booking->guest_name,
                'sublabel' => $booking->room?->room_number
                    ? "Room {$booking->room->room_number} · {$booking->status}"
                    : ucfirst((string) $booking->status),
                'href' => route('bookings.index', ['current_team' => $current_team->slug, 'search' => $booking->guest_name]),
            ];
        }

        $rooms = Room::query()
            ->where('team_id', $current_team->id)
            ->where('room_number', 'like', $like)
            ->orderBy('room_number')
            ->limit(5)
            ->get();

        foreach ($rooms as $room) {
            $results[] = [
                'type' => 'Room',
                'label' => "Room {$room->room_number}",
                'sublabel' => ucfirst((string) $room->status),
                'href' => route('rooms.index', ['current_team' => $current_team->slug]),
            ];
        }

        return response()->json(['results' => $results]);
    }
}
