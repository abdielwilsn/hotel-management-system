<?php

namespace App\Console\Commands;

use App\Enums\Ability;
use App\Models\Booking;
use App\Notifications\Bookings\BookingNeedsSettlement;
use App\Support\StayClosingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Flag bookings whose checkout date has arrived while a POS room charge is
 * still sitting unpaid on the folio — the guest's stay may not have overstayed
 * yet, but the bar tab wasn't collected and checkout is imminent or already due.
 */
class FlagUnsettledRoomCharges extends Command
{
    protected $signature = 'pos:flag-unsettled-room-charges';

    protected $description = 'Alert front desk to bookings checking out with unsettled POS room charges';

    public function handle(StayClosingService $stays): int
    {
        $bookings = Booking::query()
            ->whereNotIn('status', ['checked_out', 'cancelled'])
            ->whereNull('notified_needs_settlement_at')
            ->whereDate('check_out_date', '<=', now()->toDateString())
            ->whereHas('posOrders', function ($query): void {
                $query->where('charge_type', 'room')->where('status', 'pending');
            })
            ->with(['invoice', 'team'])
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No unsettled room charges are due at checkout.');

            return self::SUCCESS;
        }

        foreach ($bookings as $booking) {
            $balance = $stays->outstandingBalance($booking);

            Notification::send(
                $booking->team->membersWithAbility(Ability::ManageBookings),
                new BookingNeedsSettlement($booking, $balance),
            );

            Booking::query()->whereKey($booking->id)->update([
                'notified_needs_settlement_at' => now(),
            ]);
        }

        $this->info("Flagged {$bookings->count()} booking(s) with unsettled room charges.");

        return self::SUCCESS;
    }
}
