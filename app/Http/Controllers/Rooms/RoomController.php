<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\SaveRoomRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Room::class, $current_team]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'room_type' => ['nullable', 'string', Rule::in($current_team->roomTypes()->pluck('slug'))],
            'status' => ['nullable', 'string', 'in:available,reserved,occupied,maintenance,cleaning'],
            'floor' => ['nullable', 'integer', 'min:1'],
            'min_capacity' => ['nullable', 'integer', 'min:1'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $today = Carbon::today()->toDateString();

        $rooms = $current_team->rooms()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('room_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('bookings', function (Builder $bookingQuery) use ($search): void {
                            $bookingQuery->where('guest_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['room_type'] ?? null, function (Builder $query, string $roomType): void {
                $query->where('room_type', $roomType);
            })
            ->when($filters['floor'] ?? null, function (Builder $query, int $floor): void {
                $query->where('floor', $floor);
            })
            ->when($filters['min_capacity'] ?? null, function (Builder $query, int $minimumCapacity): void {
                $query->where('capacity', '>=', $minimumCapacity);
            })
            ->when($filters['max_price'] ?? null, function (Builder $query, string $maximumPrice): void {
                $query->where('price_per_night', '<=', $maximumPrice);
            })
            ->with([
                'bookings' => function ($query) use ($today): void {
                    $query
                        ->select([
                            'id',
                            'team_id',
                            'room_id',
                            'guest_name',
                            'check_in_date',
                            'check_out_date',
                            'status',
                        ])
                        ->where(function ($bookingQuery) use ($today): void {
                            $bookingQuery
                                ->where('status', 'checked_in')
                                ->orWhere(function ($reservationQuery) use ($today): void {
                                    $reservationQuery
                                        ->where(function ($statusQuery): void {
                                            $statusQuery
                                                ->where('status', 'pending')
                                                ->orWhere('status', 'confirmed');
                                        })
                                        ->whereDate('check_in_date', '<=', $today)
                                        ->whereDate('check_out_date', '>=', $today);
                                });
                        })
                        ->orderByDesc('check_in_date');
                },
            ])
            ->orderBy('room_number')
            ->get();

        $occupiedCount = $rooms
            ->filter(fn (Room $room): bool => $room->bookings->contains(fn (Booking $booking): bool => $booking->status === 'checked_in'))
            ->count();

        $reservedCount = $rooms
            ->filter(function (Room $room): bool {
                $hasCheckedInBooking = $room->bookings->contains(
                    fn (Booking $booking): bool => $booking->status === 'checked_in',
                );

                if ($hasCheckedInBooking) {
                    return false;
                }

                return $room->bookings->contains(
                    fn (Booking $booking): bool => in_array($booking->status, ['pending', 'confirmed'], true),
                );
            })
            ->count();

        $checkedInCount = Booking::query()
            ->where('team_id', $current_team->id)
            ->where('status', 'checked_in')
            ->count();

        $activeReservationCount = Booking::query()
            ->where('team_id', $current_team->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('check_in_date', '<=', $today)
            ->whereDate('check_out_date', '>=', $today)
            ->count();

        $rooms = $rooms->map(function (Room $room): array {
            /** @var Booking|null $activeCheckedInBooking */
            $activeCheckedInBooking = $room->bookings->first(
                fn (Booking $booking): bool => $booking->status === 'checked_in',
            );

            /** @var Booking|null $activeReservation */
            $activeReservation = $room->bookings->first(
                fn (Booking $booking): bool => in_array($booking->status, ['pending', 'confirmed'], true),
            );

            $derivedStatus = $room->status;
            $displayBooking = null;

            if ($activeCheckedInBooking) {
                $derivedStatus = 'occupied';
                $displayBooking = $activeCheckedInBooking;
            } elseif ($activeReservation) {
                $derivedStatus = 'reserved';
                $displayBooking = $activeReservation;
            }

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'floor' => $room->floor,
                'room_type' => $room->room_type,
                'capacity' => $room->capacity,
                'price_per_night' => $room->price_per_night,
                'status' => $derivedStatus,
                'description' => $room->description,
                'active_booking' => $displayBooking ? [
                    'id' => $displayBooking->id,
                    'guest_name' => $displayBooking->guest_name,
                    'check_in_date' => $displayBooking->check_in_date?->toDateString(),
                    'check_out_date' => $displayBooking->check_out_date?->toDateString(),
                    'status' => $displayBooking->status,
                ] : null,
            ];
        });

        if (($filters['status'] ?? null) !== null) {
            $rooms = $rooms->where('status', $filters['status']);
        }

        $rooms = $rooms->values();

        $roomTypes = $this->roomTypesFor($current_team);
        $statuses = ['available', 'occupied', 'maintenance', 'cleaning'];

        $occupiedCount = $rooms->where('status', 'occupied')->count();
        $reservedCount = $rooms->where('status', 'reserved')->count();
        $checkedInCount = $occupiedCount;
        $activeReservationCount = $reservedCount;

        return Inertia::render('rooms/Index', [
            'rooms' => $rooms,
            'roomTypes' => $roomTypes,
            'statuses' => $statuses,
            'filters' => Arr::only($filters, [
                'search',
                'room_type',
                'status',
                'floor',
                'min_capacity',
                'max_price',
            ]),
            'occupancySummary' => [
                'occupied_rooms' => $occupiedCount,
                'reserved_rooms' => $reservedCount,
                'checked_in_bookings' => $checkedInCount,
                'active_reservations' => $activeReservationCount,
            ],
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

        $roomTypes = $this->roomTypesFor($current_team);
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

    /**
     * The team's room types, with how many rooms use each one so the manager
     * UI can explain why a type can't be deleted.
     *
     * @return array<int, array{id: int, slug: string, name: string, rooms_count: int}>
     */
    private function roomTypesFor(Team $team): array
    {
        $counts = $team->rooms()
            ->selectRaw('room_type, count(*) as aggregate')
            ->groupBy('room_type')
            ->pluck('aggregate', 'room_type');

        return $team->roomTypes()
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (RoomType $type): array => [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $type->name,
                'rooms_count' => (int) ($counts[$type->slug] ?? 0),
            ])
            ->all();
    }
}
