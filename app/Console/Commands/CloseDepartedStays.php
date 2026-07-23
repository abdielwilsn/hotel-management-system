<?php

namespace App\Console\Commands;

use App\Enums\Ability;
use App\Models\Booking;
use App\Notifications\Bookings\BookingNeedsSettlement;
use App\Support\StayClosingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Close out stays whose departure time has passed.
 *
 * Rooms would otherwise stay held by guests who left days ago, because closing
 * a stay is a deliberate act at the desk and nobody is at the desk at 3am.
 */
class CloseDepartedStays extends Command
{
    protected $signature = 'stays:close-departed
                            {--dry-run : List what would be closed without touching anything}';

    protected $description = 'Check out stays whose booked departure has passed and which owe nothing';

    public function handle(StayClosingService $stays): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $departed = Booking::query()
            ->whereNotIn('status', ['checked_out', 'cancelled'])
            ->whereNotNull('check_out_at')
            ->where('check_out_at', '<=', now())
            ->with(['invoice', 'room', 'team'])
            ->get();

        if ($departed->isEmpty()) {
            $this->info('Nothing has overstayed its departure.');

            return self::SUCCESS;
        }

        // Money is settled by a person, never by a scheduled task. A stay that
        // still owes something is left open and reported instead.
        [$owing, $settled] = $departed->partition(
            fn (Booking $booking) => $stays->outstandingBalance($booking) > 0.01
        );

        foreach ($settled as $booking) {
            if ($dryRun) {
                $this->line("  would close #{$booking->id} {$booking->guest_name}");

                continue;
            }

            DB::transaction(function () use ($booking, $stays): void {
                // They were due to leave at the booked time, and nobody saw
                // them go, so that is the most honest departure we can record.
                $stays->close($booking, $booking->check_out_at);
            });
        }

        $this->info(sprintf(
            '%s %d stay(s).',
            $dryRun ? 'Would close' : 'Closed',
            $settled->count(),
        ));

        if ($owing->isNotEmpty()) {
            $this->warn("{$owing->count()} stay(s) are past departure but still owe money — the desk has to settle these:");

            $this->table(
                ['Booking', 'Guest', 'Room', 'Due since', 'Balance'],
                $owing->map(fn (Booking $booking) => [
                    $booking->id,
                    $booking->guest_name,
                    $booking->room?->room_number ?? '—',
                    $booking->check_out_at->diffForHumans(),
                    number_format($stays->outstandingBalance($booking), 2),
                ])->all(),
            );

            if (! $dryRun) {
                $this->notifyOwing($owing, $stays);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Alert front desk to bookings that are past departure and still owe
     * money — once per booking, not on every hourly run.
     *
     * @param  Collection<int, Booking>  $owing
     */
    private function notifyOwing($owing, StayClosingService $stays): void
    {
        foreach ($owing as $booking) {
            if ($booking->notified_needs_settlement_at !== null) {
                continue;
            }

            Notification::send(
                $booking->team->membersWithAbility(Ability::ManageBookings),
                new BookingNeedsSettlement($booking, $stays->outstandingBalance($booking)),
            );

            // Query builder update — bypasses the fillable guard and, more
            // importantly, doesn't touch updated_at, which nothing here is
            // measuring anyway.
            Booking::query()->whereKey($booking->id)->update([
                'notified_needs_settlement_at' => now(),
            ]);
        }
    }
}
