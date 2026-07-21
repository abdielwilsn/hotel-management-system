<?php

namespace App\Enums;

/**
 * A single, granular thing a user is allowed to do on a team.
 *
 * Abilities are the unit of permission throughout the application: policies ask
 * for them, and departments are the named bundles of them that managers edit.
 * Adding a feature means adding a case here; it never means a migration.
 *
 * Data visibility (which departments a user can see) is a separate concern and
 * lives on the membership, not here. See Membership::$data_scope.
 */
enum Ability: string
{
    /**
     * Access to the core hotel modules. POS-only staff do not have this, which
     * keeps them out of bookings, rooms, reports and the rest of the app.
     */
    case AccessHotel = 'hotel.access';

    case ViewBookings = 'bookings.view';
    case ManageBookings = 'bookings.manage';
    case DeleteBookings = 'bookings.delete';
    case RequestDiscounts = 'bookings.discounts.request';
    case ReviewDiscounts = 'bookings.discounts.review';
    case ReviewStayAdjustments = 'bookings.stay.review';

    case ViewRooms = 'rooms.view';
    case ManageRooms = 'rooms.manage';

    case ViewGuests = 'guests.view';
    case ManageGuests = 'guests.manage';
    case DeleteGuests = 'guests.delete';

    case ViewInvoices = 'invoices.view';
    case ManageInvoices = 'invoices.manage';

    case ViewPayments = 'payments.view';
    case RecordPayments = 'payments.record';
    case ManagePayments = 'payments.manage';

    case ViewExpenses = 'expenses.view';
    case ManageExpenses = 'expenses.manage';

    case ViewInventory = 'inventory.view';
    case ManageInventory = 'inventory.manage';

    case OperatePos = 'pos.operate';
    case ManagePos = 'pos.manage';

    case ViewStaff = 'staff.view';
    case ManageStaff = 'staff.manage';

    case ViewDepartments = 'departments.view';
    case ManageDepartments = 'departments.manage';

    case ViewIncidents = 'incidents.view';
    case ReportIncidents = 'incidents.report';
    case ResolveIncidents = 'incidents.resolve';

    case ViewReports = 'reports.view';
    case ViewForecasts = 'forecasts.view';

    case ManagePermissions = 'permissions.manage';

    /**
     * The human readable name shown in the permission editor.
     */
    public function label(): string
    {
        return match ($this) {
            self::AccessHotel => 'Access hotel modules',

            self::ViewBookings => 'View bookings',
            self::ManageBookings => 'Create and edit bookings',
            self::DeleteBookings => 'Delete bookings',
            self::RequestDiscounts => 'Request a discount',
            self::ReviewDiscounts => 'Approve or reject discounts',
            self::ReviewStayAdjustments => 'Approve a different number of nights',

            self::ViewRooms => 'View rooms',
            self::ManageRooms => 'Manage rooms and room types',

            self::ViewGuests => 'View guests',
            self::ManageGuests => 'Create and edit guests',
            self::DeleteGuests => 'Delete guests',

            self::ViewInvoices => 'View invoices',
            self::ManageInvoices => 'Create, edit and delete invoices',

            self::ViewPayments => 'View payments',
            self::RecordPayments => 'Record a payment',
            self::ManagePayments => 'Edit and delete payments',

            self::ViewExpenses => 'View expenses',
            self::ManageExpenses => 'Create, edit and delete expenses',

            self::ViewInventory => 'View inventory',
            self::ManageInventory => 'Manage inventory items and categories',

            self::OperatePos => 'Operate the point of sale',
            self::ManagePos => 'Manage outlets, menus and POS categories',

            self::ViewStaff => 'View staff',
            self::ManageStaff => 'Create, edit and remove staff',

            self::ViewDepartments => 'View departments',
            self::ManageDepartments => 'Create, edit and delete departments',

            self::ViewIncidents => 'View incident reports',
            self::ReportIncidents => 'File an incident report',
            self::ResolveIncidents => 'Investigate and close incidents',

            self::ViewReports => 'View reports',
            self::ViewForecasts => 'View forecasts',

            self::ManagePermissions => 'Manage permissions',
        };
    }

    /**
     * The section this ability is listed under in the permission editor.
     */
    public function group(): string
    {
        return match ($this) {
            self::AccessHotel => 'General',
            self::ViewBookings, self::ManageBookings, self::DeleteBookings,
            self::RequestDiscounts, self::ReviewDiscounts,
            self::ReviewStayAdjustments => 'Bookings',
            self::ViewRooms, self::ManageRooms => 'Rooms',
            self::ViewGuests, self::ManageGuests, self::DeleteGuests => 'Guests',
            self::ViewInvoices, self::ManageInvoices => 'Invoices',
            self::ViewPayments, self::RecordPayments, self::ManagePayments => 'Payments',
            self::ViewExpenses, self::ManageExpenses => 'Expenses',
            self::ViewInventory, self::ManageInventory => 'Inventory',
            self::OperatePos, self::ManagePos => 'Point of sale',
            self::ViewStaff, self::ManageStaff => 'Staff',
            self::ViewDepartments, self::ManageDepartments => 'Departments',
            self::ViewIncidents, self::ReportIncidents,
            self::ResolveIncidents => 'Incidents',
            self::ViewReports, self::ViewForecasts => 'Reports',
            self::ManagePermissions => 'Administration',
        };
    }

    /**
     * Every ability, as plain string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $ability) => $ability->value, self::cases());
    }

    /**
     * Every ability grouped by section, ready for the permission editor UI.
     *
     * @return array<int, array{group: string, abilities: array<int, array{value: string, label: string}>}>
     */
    public static function grouped(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $ability) => $ability->group())
            ->map(fn ($abilities, string $group) => [
                'group' => $group,
                'abilities' => $abilities
                    ->map(fn (self $ability) => [
                        'value' => $ability->value,
                        'label' => $ability->label(),
                    ])
                    ->values()
                    ->toArray(),
            ])
            ->values()
            ->toArray();
    }
}
