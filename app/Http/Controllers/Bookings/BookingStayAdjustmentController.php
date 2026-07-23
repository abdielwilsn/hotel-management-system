<?php

namespace App\Http\Controllers\Bookings;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\RequestStayAdjustmentRequest;
use App\Http\Requests\Bookings\ReviewStayAdjustmentRequest;
use App\Models\Booking;
use App\Models\BookingStayAdjustment;
use App\Models\Team;
use App\Notifications\Bookings\StayAdjustmentRequested;
use App\Notifications\Bookings\StayAdjustmentReviewed;
use App\Support\BookingStayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Requests to bill a stay for a different number of nights than the house
 * policy worked out, and the manager's decision on them.
 */
class BookingStayAdjustmentController extends Controller
{
    public function __construct(private BookingStayService $stays) {}

    /**
     * The desk asks for a different night count; a manager's own request stands.
     */
    public function store(RequestStayAdjustmentRequest $request, Team $current_team, Booking $booking): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('update', [$booking, $current_team]);

        $adjustment = $this->stays->requestAdjustment(
            $current_team,
            $booking,
            $request->user(),
            (int) $request->validated('requested_nights'),
            $request->validated('reason'),
        );

        if ($adjustment->status === 'pending') {
            Notification::send(
                $current_team->membersWithAbility(Ability::ReviewStayAdjustments),
                new StayAdjustmentRequested($adjustment),
            );
        }

        $message = $adjustment->status === 'approved'
            ? 'Stay re-priced at '.$adjustment->requested_nights.' night(s).'
            : 'Sent to a manager for approval.';

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', $message);
    }

    public function approve(
        ReviewStayAdjustmentRequest $request,
        Team $current_team,
        Booking $booking,
        BookingStayAdjustment $adjustment,
    ): RedirectResponse {
        $this->authorizeReview($current_team, $booking, $adjustment);

        $adjustment = $this->stays->approve($current_team, $adjustment, $request->user(), $request->validated('review_notes'));

        if ($adjustment->requestedBy) {
            Notification::send($adjustment->requestedBy, new StayAdjustmentReviewed($adjustment));
        }

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', 'Night count approved.');
    }

    public function reject(
        ReviewStayAdjustmentRequest $request,
        Team $current_team,
        Booking $booking,
        BookingStayAdjustment $adjustment,
    ): RedirectResponse {
        $this->authorizeReview($current_team, $booking, $adjustment);

        $adjustment = $this->stays->reject($current_team, $adjustment, $request->user(), $request->validated('review_notes'));

        if ($adjustment->requestedBy) {
            Notification::send($adjustment->requestedBy, new StayAdjustmentReviewed($adjustment));
        }

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', 'Night count left as the policy worked it out.');
    }

    /**
     * Only a reviewer may decide, and only on a request still awaiting one.
     */
    private function authorizeReview(Team $team, Booking $booking, BookingStayAdjustment $adjustment): void
    {
        $this->bookingForTeam($team, $booking);

        abort_unless($adjustment->booking_id === $booking->id, 404);
        abort_unless(request()->user()?->hasAbility(Ability::ReviewStayAdjustments, $team), 403);
        abort_unless($adjustment->status === 'pending', 422);
    }

    /**
     * Ensure the booking belongs to the team in the URL.
     */
    private function bookingForTeam(Team $team, Booking $booking): void
    {
        abort_unless($booking->team_id === $team->id, 404);
    }
}
