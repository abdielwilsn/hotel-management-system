<?php

namespace App\Support;

use App\Models\Team;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The clock a hotel runs on.
 *
 * A hotel day is not 24 hours from arrival: it runs from one checkout time to
 * the next. These three times are what turn a pair of timestamps into a number
 * of chargeable nights.
 */
final readonly class StayPolicy
{
    public const string DEFAULT_CHECK_IN_TIME = '14:00';

    public const string DEFAULT_CHECK_OUT_TIME = '12:00';

    public const string DEFAULT_EARLY_CHECK_IN_FROM = '08:00';

    public function __construct(
        /** When a standard arrival is expected, as "HH:MM". */
        public string $checkInTime = self::DEFAULT_CHECK_IN_TIME,
        /** When the hotel day ends and the room must be vacated, as "HH:MM". */
        public string $checkOutTime = self::DEFAULT_CHECK_OUT_TIME,
        /**
         * The earliest an arrival still counts as "today".
         *
         * Arriving before this has the guest sleeping in the room overnight, so
         * the night that began the evening before has been used.
         */
        public string $earlyCheckInFrom = self::DEFAULT_EARLY_CHECK_IN_FROM,
    ) {}

    /**
     * Build the policy a team runs on, falling back to the common hotel clock.
     */
    public static function forTeam(Team $team): self
    {
        return new self(
            checkInTime: $team->check_in_time ?? self::DEFAULT_CHECK_IN_TIME,
            checkOutTime: $team->check_out_time ?? self::DEFAULT_CHECK_OUT_TIME,
            earlyCheckInFrom: $team->early_check_in_from ?? self::DEFAULT_EARLY_CHECK_IN_FROM,
        );
    }

    /**
     * The checkout boundary on the calendar day of the given moment.
     */
    public function checkOutBoundaryOn(CarbonInterface $moment): CarbonImmutable
    {
        return $this->applyTime($moment, $this->checkOutTime);
    }

    /**
     * The earliest-arrival boundary on the calendar day of the given moment.
     */
    public function earlyArrivalBoundaryOn(CarbonInterface $moment): CarbonImmutable
    {
        return $this->applyTime($moment, $this->earlyCheckInFrom);
    }

    /**
     * The standard arrival time on the calendar day of the given moment.
     */
    public function checkInBoundaryOn(CarbonInterface $moment): CarbonImmutable
    {
        return $this->applyTime($moment, $this->checkInTime);
    }

    /**
     * Stamp an "HH:MM" time onto the calendar day of the given moment.
     */
    private function applyTime(CarbonInterface $moment, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return CarbonImmutable::instance($moment->toDateTime())
            ->setTime($hour, $minute);
    }
}
