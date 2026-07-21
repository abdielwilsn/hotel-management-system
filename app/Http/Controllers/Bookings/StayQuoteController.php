<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Team;
use App\Support\StayCalculator;
use App\Support\StayPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * What a stay would be billed, before anyone commits to it.
 *
 * The booking form asks this whenever the dates or times change, so the desk
 * sees "2 nights because the guest arrives at 05:00" while they are still
 * talking to the guest. It exists so the rule lives in exactly one place: a
 * second copy in JavaScript would be a copy that drifts.
 */
class StayQuoteController extends Controller
{
    public function __invoke(Request $request, Team $current_team): JsonResponse
    {
        Gate::authorize('viewAny', [Booking::class, $current_team]);

        $data = $request->validate([
            'check_in_at' => ['required', 'date'],
            'check_out_at' => ['required', 'date', 'after:check_in_at'],
            'room_id' => ['nullable', 'integer'],
        ]);

        $policy = StayPolicy::forTeam($current_team);

        $charge = (new StayCalculator($policy))->charge(
            CarbonImmutable::parse($data['check_in_at']),
            CarbonImmutable::parse($data['check_out_at']),
        );

        $rate = $this->nightlyRate($current_team, $data['room_id'] ?? null);

        return response()->json([
            ...$charge->toArray(),
            'nightly_rate' => $rate,
            'total' => $rate === null ? null : round($rate * $charge->nights, 2),
            'policy' => [
                'check_in_time' => $policy->checkInTime,
                'check_out_time' => $policy->checkOutTime,
                'early_check_in_from' => $policy->earlyCheckInFrom,
            ],
        ]);
    }

    /**
     * The nightly rate of the chosen room, when one has been picked yet.
     */
    private function nightlyRate(Team $team, ?int $roomId): ?float
    {
        if ($roomId === null) {
            return null;
        }

        $room = $team->rooms()->whereKey($roomId)->first();

        return $room === null ? null : (float) $room->price_per_night;
    }
}
