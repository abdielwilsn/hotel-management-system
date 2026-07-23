<?php

namespace App\Notifications\Bookings;

use App\Models\BookingStayAdjustment;
use App\Notifications\TeamNotification;

class StayAdjustmentRequested extends TeamNotification
{
    public function __construct(public BookingStayAdjustment $adjustment)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, adjustment_id: int, booking_id: int}
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->adjustment->booking;

        return [
            'team_id' => $this->adjustment->team_id,
            'message' => "{$booking->guest_name}'s stay was requested at {$this->adjustment->requested_nights} night(s) instead of {$this->adjustment->computed_nights} — needs your approval.",
            'url' => route('bookings.index', $booking->team->slug),
            'adjustment_id' => $this->adjustment->id,
            'booking_id' => $this->adjustment->booking_id,
        ];
    }
}
