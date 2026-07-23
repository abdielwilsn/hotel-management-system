<?php

namespace App\Http\Controllers\Bookings;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\ReviewBookingDiscountRequest;
use App\Http\Requests\Bookings\SaveBookingDiscountRequest;
use App\Models\Booking;
use App\Models\BookingDiscount;
use App\Models\Team;
use App\Notifications\Bookings\DiscountRequested;
use App\Notifications\Bookings\DiscountReviewed;
use App\Support\BookingDiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class BookingDiscountController extends Controller
{
    public function __construct(private BookingDiscountService $discounts) {}

    /**
     * Front desk requests a discount (pending), or a manager records one (auto-approved).
     */
    public function store(SaveBookingDiscountRequest $request, Team $current_team, Booking $booking): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('create', [BookingDiscount::class, $current_team]);

        $discount = $this->discounts->request($current_team, $booking, $request->user(), [
            'type' => (string) $request->validated('type'),
            'value' => $request->validated('value'),
            'reason' => $request->validated('reason'),
        ]);

        if ($discount->status === 'pending') {
            Notification::send(
                $current_team->membersWithAbility(Ability::ReviewDiscounts),
                new DiscountRequested($discount),
            );
        }

        $message = $discount->status === 'approved'
            ? 'Discount applied to the booking.'
            : 'Discount request submitted for manager approval.';

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', $message);
    }

    public function approve(ReviewBookingDiscountRequest $request, Team $current_team, Booking $booking, BookingDiscount $discount): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);
        $this->discountForBooking($booking, $discount);

        Gate::authorize('review', [BookingDiscount::class, $current_team]);

        abort_unless($discount->status === 'pending', 422);

        $this->discounts->approve($booking, $discount, $request->user(), $request->validated('review_notes'));

        if ($discount->requestedBy) {
            Notification::send($discount->requestedBy, new DiscountReviewed($discount));
        }

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', 'Discount approved.');
    }

    public function reject(ReviewBookingDiscountRequest $request, Team $current_team, Booking $booking, BookingDiscount $discount): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);
        $this->discountForBooking($booking, $discount);

        Gate::authorize('review', [BookingDiscount::class, $current_team]);

        abort_unless($discount->status === 'pending', 422);

        $this->discounts->reject($discount, $request->user(), $request->validated('review_notes'));

        if ($discount->requestedBy) {
            Notification::send($discount->requestedBy, new DiscountReviewed($discount));
        }

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', 'Discount rejected.');
    }

    private function bookingForTeam(Team $team, Booking $booking): void
    {
        if ($booking->team_id !== $team->id) {
            abort(403);
        }
    }

    private function discountForBooking(Booking $booking, BookingDiscount $discount): void
    {
        if ($discount->booking_id !== $booking->id) {
            abort(403);
        }
    }
}
