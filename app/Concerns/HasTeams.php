<?php

namespace App\Concerns;

use App\Enums\Ability;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\Membership;
use App\Models\Team;
use App\Support\TeamPermissions;
use App\Support\UserTeam;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Bumped whenever a membership or role is written. Memoised entries carry
     * the generation they were read at, so a write invalidates them without
     * needing a handle on every User instance in play.
     */
    protected static int $teamMembershipGeneration = 0;

    /**
     * Memberships memoised on this instance, keyed by team id.
     *
     * Held per instance rather than statically so that nothing leaks between
     * requests, or between tests that reuse the same primary keys.
     *
     * @var array<int, array{generation: int, membership: Membership|null}>
     */
    protected array $teamMembershipCache = [];

    /**
     * Department-derived abilities memoised on this instance, keyed by team id.
     *
     * @var array<int, array{generation: int, abilities: Collection<int, Ability>|null}>
     */
    protected array $departmentAbilityCache = [];

    /**
     * The departments this user is scoped to, when their data scope is limited.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withPivot('team_id')
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['role', 'data_scope'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.role', TeamRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->teams()
            ->where('is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->teamRole($team) === TeamRole::Owner;
    }

    /**
     * Get the user's membership of the given team.
     *
     * Memoised for the lifetime of the request because policies ask for it many
     * times per page. Membership writes flush the cache, see Membership::boot().
     */
    public function teamMembership(Team $team): ?Membership
    {
        $cached = $this->teamMembershipCache[$team->id] ?? null;

        if ($cached !== null && $cached['generation'] === static::$teamMembershipGeneration) {
            return $cached['membership'];
        }

        $membership = $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first();

        $this->teamMembershipCache[$team->id] = [
            'generation' => static::$teamMembershipGeneration,
            'membership' => $membership,
        ];

        return $membership;
    }

    /**
     * Invalidate every memoised membership.
     */
    public static function flushTeamMembershipCache(): void
    {
        static::$teamMembershipGeneration++;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?TeamRole
    {
        return $this->teamMembership($team)?->role;
    }

    /**
     * Get the abilities the user holds on the given team.
     *
     * Departments are the permission groups: what someone may do follows from
     * the departments they work in. Owners and admins run the whole hotel, and
     * anyone not yet assigned to a department falls back to their base role so
     * that a half-configured team is never locked out.
     *
     * @return Collection<int, Ability>
     */
    public function teamAbilities(Team $team): Collection
    {
        $membership = $this->teamMembership($team);

        if ($membership === null) {
            return collect();
        }

        if ($membership->role?->isAtLeast(TeamRole::Admin)) {
            return collect(Ability::cases());
        }

        $departmentAbilities = $this->departmentAbilities($team);

        if ($departmentAbilities !== null) {
            return $departmentAbilities;
        }

        return $membership->abilities();
    }

    /**
     * The union of the abilities granted by the user's departments.
     *
     * Returns null when the user belongs to no department, which callers treat
     * as "fall back" rather than "grants nothing".
     *
     * @return Collection<int, Ability>|null
     */
    protected function departmentAbilities(Team $team): ?Collection
    {
        $cached = $this->departmentAbilityCache[$team->id] ?? null;

        if ($cached !== null && $cached['generation'] === static::$teamMembershipGeneration) {
            return $cached['abilities'];
        }

        $departments = $this->departments()
            ->where('department_user.team_id', $team->id)
            ->get();

        $abilities = $departments->isEmpty()
            ? null
            : $departments
                ->flatMap(fn (Department $department) => $department->abilities ?? [])
                ->unique()
                ->map(fn (string $ability) => Ability::tryFrom($ability))
                ->filter()
                ->values();

        $this->departmentAbilityCache[$team->id] = [
            'generation' => static::$teamMembershipGeneration,
            'abilities' => $abilities,
        ];

        return $abilities;
    }

    /**
     * Determine if the user holds the given ability on the given team.
     *
     * This is the single question every policy asks. Non-members hold nothing.
     */
    public function hasAbility(Ability $ability, ?Team $team = null): bool
    {
        $team ??= $this->currentTeam;

        if ($team === null) {
            return false;
        }

        return $this->teamAbilities($team)->contains($ability);
    }

    /**
     * The departments the user's records are limited to on the given team.
     *
     * Returns null when the user sees everything, which callers treat as "do not
     * filter" rather than "filter to nothing".
     *
     * @return Collection<int, int>|null
     */
    public function visibleDepartmentIds(Team $team): ?Collection
    {
        $membership = $this->teamMembership($team);

        if ($membership === null || $membership->seesAllDepartments()) {
            return null;
        }

        return $this->departments()
            ->where('department_user.team_id', $team->id)
            ->pluck('departments.id');
    }

    /**
     * Determine if the user may act on records in the given department.
     */
    public function canAccessDepartment(Team $team, ?int $departmentId): bool
    {
        $visible = $this->visibleDepartmentIds($team);

        if ($visible === null) {
            return true;
        }

        return $departmentId !== null && $visible->contains($departmentId);
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teams()
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $role = $this->teamRole($team);

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentTeam($team),
            currency: $team->currency ?? 'NGN',
            locale: $team->locale ?? 'en-NG',
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        $role = $this->teamRole($team);

        return new TeamPermissions(
            canUpdateTeam: $role?->hasPermission(TeamPermission::UpdateTeam) ?? false,
            canDeleteTeam: $role?->hasPermission(TeamPermission::DeleteTeam) ?? false,
            canAddMember: $role?->hasPermission(TeamPermission::AddMember) ?? false,
            canUpdateMember: $role?->hasPermission(TeamPermission::UpdateMember) ?? false,
            canRemoveMember: $role?->hasPermission(TeamPermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->hasPermission(TeamPermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->hasPermission(TeamPermission::CancelInvitation) ?? false,
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        return $this->teamRole($team)?->hasPermission($permission) ?? false;
    }
}
