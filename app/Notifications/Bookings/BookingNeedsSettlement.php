<?php

namespace App\Notifications\Bookings;

use App\Models\Booking;
use App\Notifications\TeamNotification;

class BookingNeedsSettlement extends TeamNotification
{
    public function __construct(public Booking $booking, public float $balance)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, booking_id: int, balance: float}
     */
    public function toArray(object $notifiable): array
    {
        $balance = number_format($this->balance, 2);

        return [
            'team_id' => $this->booking->team_id,
            'message' => "{$this->booking->guest_name} owes {$balance} and needs to settle before checkout.",
            'url' => route('bookings.index', $this->booking->team->slug),
            'booking_id' => $this->booking->id,
            'balance' => $this->balance,
        ];
    }
}
