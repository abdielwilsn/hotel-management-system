<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Pos = 'pos';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pos => 'POS Staff',
            default => ucfirst($this->value),
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::Admin => [
                TeamPermission::UpdateTeam,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
            ],
            self::Member => [],
            self::Pos => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * The abilities a member of this role has when no custom role is assigned.
     *
     * These mirror the permissions the application shipped with, so a team that
     * has never touched the role editor behaves exactly as it always did.
     *
     * @return array<int, Ability>
     */
    public function defaultAbilities(): array
    {
        return match ($this) {
            self::Owner, self::Admin => Ability::cases(),
            self::Member => [
                Ability::AccessHotel,
                Ability::ViewBookings,
                Ability::ManageBookings,
                Ability::RequestDiscounts,
                Ability::ViewRooms,
                Ability::ViewGuests,
                Ability::ManageGuests,
                Ability::ViewInvoices,
                Ability::ViewPayments,
                Ability::RecordPayments,
                Ability::OperatePos,
            ],
            self::Pos => [
                Ability::OperatePos,
            ],
        };
    }

    /**
     * The default abilities as plain string values.
     *
     * @return array<int, string>
     */
    public function defaultAbilityValues(): array
    {
        return array_map(fn (Ability $ability) => $ability->value, $this->defaultAbilities());
    }

    /**
     * A short description of the role, used when seeding the editable system roles.
     */
    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access to everything, including billing and team deletion.',
            self::Admin => 'Manages the hotel day to day, including staff, reports and permissions.',
            self::Member => 'Front desk and general staff. Handles bookings, guests and payments.',
            self::Pos => 'Works the bar or restaurant terminal only.',
        };
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Member => 1,
            self::Pos => 0,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned to team members (excludes Owner).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
