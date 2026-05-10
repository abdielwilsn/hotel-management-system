<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Department $department, Team $team): bool
    {
        return $user->belongsToTeam($team) && $department->team_id === $team->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Department $department, Team $team): bool
    {
        return $department->team_id === $team->id
            && $this->hasAdminPrivileges($user, $team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Department $department, Team $team): bool
    {
        return $department->team_id === $team->id
            && $this->hasAdminPrivileges($user, $team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Department $department): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Department $department): bool
    {
        return false;
    }

    /**
     * Determine if the user has at least admin privileges for a team.
     */
    protected function hasAdminPrivileges(User $user, Team $team): bool
    {
        $role = $user->teamRole($team);

        return $role?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
