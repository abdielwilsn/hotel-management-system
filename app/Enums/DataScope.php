<?php

namespace App\Enums;

/**
 * Which records a member may act on, independent of what actions they may take.
 *
 * Abilities answer "what can they do"; this answers "to which records". Keeping
 * the two apart means a department reorganisation is a reassignment, not a
 * permission rewrite.
 */
enum DataScope: string
{
    /** Every record on the team, regardless of department. */
    case All = 'all';

    /** Only records belonging to the departments the member is assigned to. */
    case Departments = 'departments';

    /**
     * Get the display label for the scope.
     */
    public function label(): string
    {
        return match ($this) {
            self::All => 'All departments',
            self::Departments => 'Assigned departments only',
        };
    }

    /**
     * Get the scopes that can be assigned to a member.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return array_map(
            fn (self $scope) => ['value' => $scope->value, 'label' => $scope->label()],
            self::cases(),
        );
    }
}
