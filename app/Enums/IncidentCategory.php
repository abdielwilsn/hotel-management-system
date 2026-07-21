<?php

namespace App\Enums;

/**
 * The kind of thing that happened, so incidents can be counted by type.
 */
enum IncidentCategory: string
{
    case GuestComplaint = 'guest_complaint';
    case Maintenance = 'maintenance';
    case Safety = 'safety';
    case Security = 'security';
    case Theft = 'theft';
    case StockLoss = 'stock_loss';
    case StaffConduct = 'staff_conduct';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GuestComplaint => 'Guest complaint',
            self::Maintenance => 'Maintenance fault',
            self::Safety => 'Health and safety',
            self::Security => 'Security',
            self::Theft => 'Theft or loss',
            self::StockLoss => 'Stock discrepancy',
            self::StaffConduct => 'Staff conduct',
            self::Other => 'Other',
        };
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
