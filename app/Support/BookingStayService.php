<?php

namespace App\Support;

use App\Enums\Ability;
use App\Models\Booking;
use App\Models\BookingStayAdjustment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place a stay is priced.
 *
 * Nights used to be worked out inline wherever a booking was written, which is
 * how the same stay could be billed two ways. Everything now comes through here.
 */
class BookingStayService
{
    public function __construct(private BookingInvoiceService $invoices) {}

    /**
     * Work out and store what a booking is billed for.
     *
     * An approved adjustment wins over the computed figure; that is the whole
     * point of the approval. Anything still pending does not touch the bill.
     */
    public function apply(Team $team, Booking $booking): Booking
    {
        $policy = StayPolicy::forTeam($team);
        $charge = $booking->computedStayCharge($policy);

        $approved = $booking->approvedStayAdjustment();
        $nights = $approved?->requested_nights ?? $charge->nights;

        $basis = $approved !== null
            ? $this->explainApproval($approved, $charge)
            : $charge->basis;

        $booking->forceFill([
            'chargeable_nights' => $nights,
            'nights_basis' => $basis,
            'total_amount' => round((float) $booking->price_per_night * $nights, 2),
        ])->save();

        // The bill has to move with the booking, or an approved waiver would
        // leave the guest still being charged the original number of nights.
        $invoice = $this->invoices->sync($team, $booking);
        $this->invoices->refreshPaidAmount($invoice->fresh());

        return $booking;
    }

    /**
     * Ask for a stay to be billed for a different number of nights.
     *
     * A request from somebody who could approve it anyway is applied at once;
     * making them raise it and then sign it off would be ceremony.
     */
    public function requestAdjustment(
        Team $team,
        Booking $booking,
        User $actor,
        int $requestedNights,
        ?string $reason = null,
    ): BookingStayAdjustment {
        $charge = $booking->computedStayCharge(StayPolicy::forTeam($team));
        $canReview = $actor->hasAbility(Ability::ReviewStayAdjustments, $team);

        return DB::transaction(function () use (
            $team, $booking, $actor, $requestedNights, $reason, $charge, $canReview
        ): BookingStayAdjustment {
            $this->cancelPending($booking);

            $adjustment = $booking->stayAdjustments()->create([
                'team_id' => $team->id,
                'computed_nights' => $charge->nights,
                'requested_nights' => $requestedNights,
                'basis' => $charge->basis,
                'reason' => $reason,
                'status' => $canReview ? 'approved' : 'pending',
                'requested_by_user_id' => $actor->id,
                'reviewed_by_user_id' => $canReview ? $actor->id : null,
                'reviewed_at' => $canReview ? now() : null,
            ]);

            if ($canReview) {
                $this->apply($team, $booking->fresh());
            }

            return $adjustment;
        });
    }

    /**
     * Approve a pending request, which re-prices the stay.
     */
    public function approve(
        Team $team,
        BookingStayAdjustment $adjustment,
        User $reviewer,
        ?string $notes = null,
    ): BookingStayAdjustment {
        return DB::transaction(function () use ($team, $adjustment, $reviewer, $notes): BookingStayAdjustment {
            $adjustment->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->apply($team, $adjustment->booking()->first());

            return $adjustment->fresh();
        });
    }

    /**
     * Turn a request down, leaving the stay priced by the policy.
     */
    public function reject(
        Team $team,
        BookingStayAdjustment $adjustment,
        User $reviewer,
        ?string $notes = null,
    ): BookingStayAdjustment {
        return DB::transaction(function () use ($team, $adjustment, $reviewer, $notes): BookingStayAdjustment {
            $adjustment->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->apply($team, $adjustment->booking()->first());

            return $adjustment->fresh();
        });
    }

    /**
     * Drop any outstanding request, so only one is ever in flight.
     */
    public function cancelPending(Booking $booking): void
    {
        $booking->stayAdjustments()->pending()->update(['status' => 'cancelled']);
    }

    /**
     * Record why the bill differs from what the policy worked out.
     */
    private function explainApproval(BookingStayAdjustment $adjustment, StayCharge $charge): string
    {
        $waived = $adjustment->nightsWaived();
        $nightLabel = $adjustment->requested_nights === 1 ? '1 night' : "{$adjustment->requested_nights} nights";

        $movement = $waived > 0
            ? "reduced from {$charge->nights}"
            : "raised from {$charge->nights}";

        return "{$nightLabel}: {$movement} by manager approval. {$charge->basis}";
    }
}
