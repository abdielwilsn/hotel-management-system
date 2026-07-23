<?php

namespace App\Notifications\Bookings;

use App\Models\BookingStayAdjustment;
use App\Notifications\TeamNotification;

class StayAdjustmentReviewed extends TeamNotification
{
    public function __construct(public BookingStayAdjustment $adjustment)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, adjustment_id: int, booking_id: int, status: string}
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->adjustment->booking;
        $verb = $this->adjustment->status === 'approved' ? 'approved' : 'rejected';

        return [
            'team_id' => $this->adjustment->team_id,
            'message' => "Your night-count request for {$booking->guest_name} was {$verb}.",
            'url' => route('bookings.index', $booking->team->slug),
            'adjustment_id' => $this->adjustment->id,
            'booking_id' => $this->adjustment->booking_id,
            'status' => $this->adjustment->status,
        ];
    }
}
