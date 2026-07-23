<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Team;
use Carbon\Carbon;

/**
 * Keeps a booking's invoice in step with the booking.
 *
 * Extracted from the booking controller because the bill has to follow the
 * booking from anywhere it can change, not only from the routes that happen to
 * live on that controller. A re-priced stay that leaves the invoice untouched
 * is a guest being charged the wrong amount.
 */
class BookingInvoiceService
{
    /**
     * Create or update the invoice that mirrors this booking.
     */
    public function sync(Team $team, Booking $booking): Invoice
    {
        $roomSubtotal = round((float) $booking->total_amount, 2);
        // The discount was negotiated against the room rate, not the guest's
        // bar tab, so it applies before POS charges are added on top.
        $discountAmount = $this->approvedDiscountAmount($booking, $roomSubtotal);
        // Summed regardless of the order's own status — that status is a
        // display flag for whether it's been settled, not whether it counts
        // toward the bill. Recomputed from scratch every time so a charge
        // can never be dropped by a later sync().
        $posChargesTotal = round((float) $booking->posOrders()->where('charge_type', 'room')->sum('total'), 2);

        $subtotal = round($roomSubtotal + $posChargesTotal, 2);
        $totalAmount = round(max($roomSubtotal - $discountAmount, 0) + $posChargesTotal, 2);

        /** @var Invoice $invoice */
        $invoice = $team->invoices()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'invoice_number' => $this->generateInvoiceNumber($team),
                'guest_name' => $booking->guest_name,
                'guest_email' => $booking->guest_email,
                'issue_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::parse($booking->check_in_date)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'status' => 'issued',
                'notes' => 'Auto-generated from booking.',
            ],
        );

        $invoice->update([
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'due_date' => Carbon::parse($booking->check_out_date)->toDateString(),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
        ]);

        return $invoice;
    }

    /**
     * Recompute what has actually been paid against an invoice.
     */
    public function refreshPaidAmount(Invoice $invoice): void
    {
        $paidAmount = (float) $invoice->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $status = $invoice->statusFor($paidAmount);

        $invoice->update([
            'paid_amount' => round($paidAmount, 2),
            'status' => $status,
        ]);

        // Once the whole bill is paid, any POS charges the front desk was
        // carrying against the room have been settled too — flip them so
        // they stop showing as outstanding on the folio.
        if ($status === 'paid') {
            PosOrder::query()
                ->where('booking_id', $invoice->booking_id)
                ->where('charge_type', 'room')
                ->where('status', 'pending')
                ->update(['status' => 'paid', 'paid_at' => now()]);
        }
    }

    /**
     * The currency value of the booking's approved discount, computed live so a
     * percentage discount keeps tracking the subtotal.
     */
    public function approvedDiscountAmount(Booking $booking, float $subtotal): float
    {
        $discount = $booking->approvedDiscount()->first();

        return $discount ? $discount->computeAmount($subtotal) : 0.0;
    }

    /**
     * A unique, human-readable invoice number for the team.
     */
    public function generateInvoiceNumber(Team $team): string
    {
        $teamCode = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $team->slug), 0, 4));
        $teamCode = $teamCode !== '' ? $teamCode : 'TEAM';

        do {
            $candidate = sprintf(
                '%s-INV-%s-%04d',
                $teamCode,
                Carbon::now()->format('ymd'),
                random_int(1, 9999),
            );
        } while ($team->invoices()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
