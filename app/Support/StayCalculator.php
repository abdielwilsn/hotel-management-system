<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns a pair of timestamps into a number of chargeable nights.
 *
 * A stay is not billed by elapsed hours. The hotel sells *hotel days*, which run
 * from one checkout time to the next, so 14:00 to noon the next day is a single
 * night even though it is only 22 hours, and 08:00 to noon the same day is also
 * a single night at just four.
 *
 * On top of that sits one rule the clock alone cannot express: a guest who
 * arrives before the early-arrival boundary has slept in the room, so the night
 * that began the evening before has been used and is charged.
 */
final readonly class StayCalculator
{
    public function __construct(private StayPolicy $policy) {}

    /**
     * Work out what a stay between two moments costs in nights.
     */
    public function charge(CarbonInterface $arrival, CarbonInterface $departure): StayCharge
    {
        $arrival = CarbonImmutable::instance($arrival->toDateTime());
        $departure = CarbonImmutable::instance($departure->toDateTime());

        $hotelDays = $this->hotelDaysBetween($arrival, $departure);
        $consumedPreviousNight = $this->arrivedBeforeMorning($arrival);

        $nights = max(1, $hotelDays + ($consumedPreviousNight ? 1 : 0));

        return new StayCharge(
            nights: $nights,
            hotelDays: $hotelDays,
            consumedPreviousNight: $consumedPreviousNight,
            basis: $this->explain($arrival, $departure, $nights, $consumedPreviousNight),
        );
    }

    /**
     * How many hotel days the stay covers.
     *
     * Each hotel day is labelled by the date it starts on, so the answer is
     * simply the number of day-labels the stay touches.
     */
    private function hotelDaysBetween(CarbonImmutable $arrival, CarbonImmutable $departure): int
    {
        $first = $this->hotelDayOf($arrival);
        $last = $this->hotelDayOf($departure->subSecond());

        return (int) $first->diffInDays($last) + 1;
    }

    /**
     * The hotel day a moment falls in, as the date that day started on.
     *
     * Anything before the checkout time still belongs to the day before: at
     * 08:00 the previous guest has not even left yet.
     */
    private function hotelDayOf(CarbonImmutable $moment): CarbonImmutable
    {
        $boundary = $this->policy->checkOutBoundaryOn($moment);

        return $moment->lt($boundary)
            ? $moment->subDay()->startOfDay()
            : $moment->startOfDay();
    }

    /**
     * Whether the guest turned up early enough to have used the night before.
     */
    private function arrivedBeforeMorning(CarbonImmutable $arrival): bool
    {
        return $arrival->lt($this->policy->earlyArrivalBoundaryOn($arrival));
    }

    /**
     * Put the night count into words a guest at the desk would accept.
     */
    private function explain(
        CarbonImmutable $arrival,
        CarbonImmutable $departure,
        int $nights,
        bool $consumedPreviousNight,
    ): string {
        $window = $arrival->format('D j M H:i').' to '.$departure->format('D j M H:i');
        $nightLabel = $nights === 1 ? '1 night' : "{$nights} nights";

        if ($consumedPreviousNight) {
            return "{$nightLabel}: arrived at {$arrival->format('H:i')}, before the "
                ."{$this->policy->earlyCheckInFrom} early check-in time, so the previous "
                ."night is included ({$window}).";
        }

        if ($arrival->lt($this->policy->checkInBoundaryOn($arrival))) {
            return "{$nightLabel}: early check-in at {$arrival->format('H:i')} "
                ."({$window}).";
        }

        return "{$nightLabel}: {$window}.";
    }
}
