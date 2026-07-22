<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Closing a stay: marking it done, recording when the guest left, and handing
 * the room back.
 *
 * Shared by the front desk's Check Out button and the overnight sweep, so the
 * two cannot drift apart on what "checked out" actually means.
 */
class StayClosingService
{
    /**
     * Close the stay as of the given moment.
     */
    public function close(Booking $booking, CarbonInterface $departedAt, ?int $actorId = null): Booking
    {
        $booking->update([
            'status' => 'checked_out',
            'checked_out_at' => $departedAt,
            'updated_by_user_id' => $actorId,
        ]);

        $this->releaseRoom($booking->room()->first(), $booking->id);

        return $booking;
    }

    /**
     * Hand the room back, unless another stay still holds it.
     */
    public function releaseRoom(?Room $room, ?int $excludedBookingId = null): void
    {
        if (! $room) {
            return;
        }

        $stillHeld = Booking::query()
            ->where('room_id', $room->id)
            ->when($excludedBookingId, fn (Builder $query, int $id) => $query->where('id', '!=', $id))
            ->whereIn('status', ['checked_in', 'pending', 'confirmed'])
            ->exists();

        if (! $stillHeld) {
            $room->update(['status' => 'available']);
        }
    }

    /**
     * What the guest still owes, to the nearest kobo.
     */
    public function outstandingBalance(Booking $booking): float
    {
        $invoice = $booking->invoice()->first();

        if ($invoice === null) {
            return 0.0;
        }

        return round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
    }
}
