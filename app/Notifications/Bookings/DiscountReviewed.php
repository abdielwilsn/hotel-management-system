<?php

namespace App\Notifications\Bookings;

use App\Models\BookingDiscount;
use App\Notifications\TeamNotification;

class DiscountReviewed extends TeamNotification
{
    public function __construct(public BookingDiscount $discount)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, discount_id: int, booking_id: int, status: string}
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->discount->booking;
        $verb = $this->discount->status === 'approved' ? 'approved' : 'rejected';

        return [
            'team_id' => $this->discount->team_id,
            'message' => "Your discount request for {$booking->guest_name} was {$verb}.",
            'url' => route('bookings.index', $booking->team->slug),
            'discount_id' => $this->discount->id,
            'booking_id' => $this->discount->booking_id,
            'status' => $this->discount->status,
        ];
    }
}
