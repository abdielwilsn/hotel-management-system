<?php

namespace App\Enums;

/**
 * Where a booking has got to, in the words the front desk actually uses.
 *
 * The stored values are unchanged; what this fixes is the vocabulary. "Pending"
 * and "Confirmed" describe the paperwork, not what the clerk is doing, so at the
 * desk they read as Reservation (the guest is coming later) and Checked In (the
 * guest is standing here).
 */
enum BookingStatus: string
{
    /** Booked for a future date, not yet paid in full. */
    case Pending = 'pending';

    /** Booked and settled, guest still to arrive. */
    case Confirmed = 'confirmed';

    /** Guest has arrived and holds the room. */
    case CheckedIn = 'checked_in';

    /** Guest has left and the room is released. */
    case CheckedOut = 'checked_out';

    case Cancelled = 'cancelled';

    /**
     * The label staff see anywhere this status is shown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Reservation',
            self::Confirmed => 'Reservation (paid)',
            self::CheckedIn => 'Checked In',
            self::CheckedOut => 'Checked Out',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * A line of help for the two choices offered when taking a booking.
     */
    public function creationHint(): ?string
    {
        return match ($this) {
            self::Pending => 'The guest is arriving later. The room stays available until they check in.',
            self::CheckedIn => 'The guest is here now. The room will be marked occupied straight away.',
            default => null,
        };
    }

    /**
     * The only two things a clerk is ever doing when they take a booking:
     * writing it down for later, or checking somebody in at the desk.
     *
     * @return array<int, array{value: string, label: string, hint: string|null}>
     */
    public static function creatable(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->value === self::Pending->value ? 'Reservation' : $status->label(),
                'hint' => $status->creationHint(),
            ],
            [self::Pending, self::CheckedIn],
        );
    }

    /**
     * Every status with its label, for filters and badges.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }

    /**
     * A value-to-label map, for the frontend to render any status it meets.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }
}
