<?php

namespace App\Support;

use App\Enums\Ability;
use App\Models\Booking;
use App\Models\BookingDiscount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingDiscountService
{
    /**
     * Record a discount request for a booking.
     *
     * A request from someone who cannot review discounts is stored as `pending`
     * and does not touch the bill. A reviewer's own request is auto-approved and
     * applied immediately, since they could simply approve it themselves.
     *
     * @param  array{type: string, value: float|int|string, reason?: string|null}  $data
     */
    public function request(Team $team, Booking $booking, User $actor, array $data): BookingDiscount
    {
        $isManager = $actor->hasAbility(Ability::ReviewDiscounts, $team);

        return DB::transaction(function () use ($team, $booking, $actor, $data, $isManager): BookingDiscount {
            $this->cancelPending($booking);

            $discount = new BookingDiscount([
                'type' => $data['type'],
                'value' => $data['value'],
                'reason' => $data['reason'] ?? null,
                'requested_by_user_id' => $actor->id,
                'status' => 'pending',
            ]);
            $discount->team_id = $team->id;
            $discount->booking_id = $booking->id;
            $discount->amount = $discount->computeAmount((float) $booking->total_amount);

            if ($isManager) {
                $discount->status = 'approved';
                $discount->reviewed_by_user_id = $actor->id;
                $discount->reviewed_at = now();
            }

            $discount->save();

            if ($discount->status === 'approved') {
                $this->applyToInvoice($booking);
            }

            return $discount;
        });
    }

    /**
     * Approve a pending discount and apply it to the booking's invoice.
     */
    public function approve(Booking $booking, BookingDiscount $discount, User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($booking, $discount, $reviewer, $notes): void {
            $this->supersedeApproved($booking, $discount->id);

            $discount->update([
                'status' => 'approved',
                'amount' => $discount->computeAmount((float) $booking->total_amount),
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->applyToInvoice($booking);
        });
    }

    /**
     * Reject a pending discount. The bill is unaffected.
     */
    public function reject(BookingDiscount $discount, User $reviewer, ?string $notes = null): void
    {
        $discount->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Recompute the booking invoice's discount and total from the approved discount,
     * preserving payments and re-deriving the payment status.
     */
    private function applyToInvoice(Booking $booking): void
    {
        $invoice = $booking->invoice()->first();

        if ($invoice === null) {
            return;
        }

        $subtotal = round((float) $booking->total_amount, 2);
        $discount = $booking->approvedDiscount()->first();
        $discountAmount = $discount ? $discount->computeAmount($subtotal) : 0.0;
        $total = round(max($subtotal - $discountAmount, 0), 2);

        $paidAmount = (float) $invoice->paid_amount;

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $total,
            'status' => $invoice->statusFor($paidAmount),
        ]);
    }

    private function cancelPending(Booking $booking): void
    {
        $booking->discounts()
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'reviewed_at' => now(),
            ]);
    }

    private function supersedeApproved(Booking $booking, int $exceptId): void
    {
        $booking->discounts()
            ->where('status', 'approved')
            ->where('id', '!=', $exceptId)
            ->update([
                'status' => 'cancelled',
                'reviewed_at' => now(),
            ]);
    }
}
