<?php

namespace App\Models;

use App\Enums\Ability;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['team_id', 'name', 'description', 'manager_id', 'status', 'abilities'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * What a brand new department can do until a manager says otherwise.
     *
     * Deliberately bare: a new department should grant nothing beyond getting
     * into the app, so creating one can never silently hand out access.
     *
     * @var array<int, string>
     */
    public const array DEFAULT_ABILITIES = [
        'hotel.access',
        'incidents.view',
        'incidents.report',
    ];

    /**
     * Starting permissions for the departments a hotel usually runs.
     *
     * Matched on name when a department is created, purely as a convenience so
     * managers start from something sensible rather than an empty list.
     *
     * @return array<string, array<int, Ability>>
     */
    public static function abilityPresets(): array
    {
        return [
            'front desk' => [
                Ability::ViewIncidents,
                Ability::ReportIncidents,
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
            ],
            'housekeeping' => [
                Ability::ViewIncidents,
                Ability::ReportIncidents,
                Ability::AccessHotel,
                Ability::ViewRooms,
                Ability::ManageRooms,
                Ability::ViewBookings,
            ],
            'finance' => [
                Ability::ViewIncidents,
                Ability::ReportIncidents,
                Ability::ResolveIncidents,
                Ability::AccessHotel,
                Ability::ViewBookings,
                Ability::ViewInvoices,
                Ability::ManageInvoices,
                Ability::ViewPayments,
                Ability::RecordPayments,
                Ability::ManagePayments,
                Ability::ViewExpenses,
                Ability::ManageExpenses,
                Ability::ReviewDiscounts,
                Ability::ReviewStayAdjustments,
                Ability::ViewReports,
                Ability::ViewForecasts,
            ],
            'operations' => [
                Ability::ViewIncidents,
                Ability::ReportIncidents,
                Ability::ResolveIncidents,
                Ability::AccessHotel,
                Ability::ViewBookings,
                Ability::ManageBookings,
                Ability::ViewRooms,
                Ability::ManageRooms,
                Ability::ViewGuests,
                Ability::ManageGuests,
                Ability::ViewStaff,
                Ability::ManageStaff,
                Ability::ViewDepartments,
                Ability::ReviewStayAdjustments,
                Ability::ViewInventory,
                Ability::ManageInventory,
                Ability::ViewReports,
            ],
            'main bar' => [
                Ability::ViewIncidents,
                Ability::ReportIncidents,
                Ability::OperatePos,
                Ability::ViewInventory,
            ],
            'restaurant' => [
                Ability::ViewIncidents,
                Ability::ReportIncidents,
                Ability::OperatePos,
                Ability::ViewInventory,
            ],
            'kitchen' => [
                Ability::ViewIncidents,
                Ability::ReportIncidents,
                Ability::OperatePos,
                Ability::ViewInventory,
            ],
        ];
    }

    /**
     * The preset abilities for a department name, or the bare default.
     *
     * @return array<int, string>
     */
    public static function presetAbilitiesFor(string $name): array
    {
        $preset = static::abilityPresets()[mb_strtolower(trim($name))] ?? null;

        if ($preset === null) {
            return static::DEFAULT_ABILITIES;
        }

        return array_map(fn (Ability $ability) => $ability->value, $preset);
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        // A department is a permission group, so it always needs a set of
        // abilities; new ones start from the preset for their name.
        static::creating(function (Department $department) {
            if ($department->abilities === null) {
                $department->abilities = static::presetAbilitiesFor((string) $department->name);
            }
        });

        static::saved(fn () => User::flushTeamMembershipCache());
        static::deleted(fn () => User::flushTeamMembershipCache());
    }

    /**
     * Determine if this department grants the given ability.
     */
    public function hasAbility(Ability $ability): bool
    {
        return in_array($ability->value, $this->abilities ?? [], strict: true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
        ];
    }

    /**
     * Scope departments that belong to the given team.
     */
    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->id);
    }

    /**
     * Limit the query to the departments the user is allowed to see.
     *
     * Mirrors BelongsToDepartment for the department list itself, where the
     * scoped column is the primary key rather than a foreign key.
     */
    public function scopeVisibleTo(Builder $query, User $user, Team $team): void
    {
        $departmentIds = $user->visibleDepartmentIds($team);

        if ($departmentIds === null) {
            return;
        }

        $query->whereIn($this->qualifyColumn('id'), $departmentIds);
    }

    /**
     * Get the users whose data scope is limited to this department.
     *
     * @return BelongsToMany<User, $this>
     */
    public function scopedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->withPivot('team_id')
            ->withTimestamps();
    }

    /**
     * Get the team that owns this department.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the manager assigned to this department.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
