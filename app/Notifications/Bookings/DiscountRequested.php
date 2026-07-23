<?php

namespace App\Notifications\Bookings;

use App\Models\BookingDiscount;
use App\Notifications\TeamNotification;

class DiscountRequested extends TeamNotification
{
    public function __construct(public BookingDiscount $discount)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, discount_id: int, booking_id: int}
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->discount->booking;
        $value = $this->discount->type === 'percentage'
            ? "{$this->discount->value}%"
            : number_format((float) $this->discount->value, 2);

        return [
            'team_id' => $this->discount->team_id,
            'message' => "{$booking->guest_name} was offered a {$value} discount — needs your approval.",
            'url' => route('bookings.index', $booking->team->slug),
            'discount_id' => $this->discount->id,
            'booking_id' => $this->discount->booking_id,
        ];
    }
}
