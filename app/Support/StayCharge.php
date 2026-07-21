<?php

namespace App\Support;

/**
 * What a stay works out to, and why.
 *
 * The reason travels with the number so a bill can always be explained at the
 * desk: "two nights because the guest arrived at 05:00" is defensible, "two
 * nights" on its own is an argument waiting to happen.
 */
final readonly class StayCharge
{
    public function __construct(
        /** Nights the guest is charged for under the policy. */
        public int $nights,
        /** Whole hotel days (checkout time to checkout time) the stay covers. */
        public int $hotelDays,
        /** Whether an extra night was added for arriving before the early boundary. */
        public bool $consumedPreviousNight,
        /** A sentence explaining how the night count was reached. */
        public string $basis,
    ) {}

    /**
     * Whether charging the requested number of nights needs a manager's sign-off.
     *
     * Only undercharging needs approval. Charging the computed amount is just
     * the policy running, and charging more is not something the desk can do by
     * accident: it still lands here because it is a deviation either way.
     */
    public function requiresApprovalFor(int $requestedNights): bool
    {
        return $requestedNights !== $this->nights;
    }

    /**
     * @return array{nights: int, hotel_days: int, consumed_previous_night: bool, basis: string}
     */
    public function toArray(): array
    {
        return [
            'nights' => $this->nights,
            'hotel_days' => $this->hotelDays,
            'consumed_previous_night' => $this->consumedPreviousNight,
            'basis' => $this->basis,
        ];
    }
}
