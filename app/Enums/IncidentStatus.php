<?php

namespace App\Enums;

/**
 * Where an incident has got to. Open and investigating both still need someone.
 */
enum IncidentStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Investigating => 'Investigating',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }

    /**
     * Whether the incident is still somebody's problem.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Investigating], strict: true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
