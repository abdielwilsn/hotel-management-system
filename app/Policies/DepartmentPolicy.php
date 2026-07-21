<?php

namespace App\Policies;

use App\Enums\Ability;
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
        return $user->hasAbility(Ability::ViewDepartments, $team);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Department $department, Team $team): bool
    {
        return $department->team_id === $team->id
            && $user->hasAbility(Ability::ViewDepartments, $team)
            && $user->canAccessDepartment($team, $department->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManageDepartments, $team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Department $department, Team $team): bool
    {
        return $department->team_id === $team->id
            && $user->hasAbility(Ability::ManageDepartments, $team)
            && $user->canAccessDepartment($team, $department->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Department $department, Team $team): bool
    {
        return $department->team_id === $team->id
            && $user->hasAbility(Ability::ManageDepartments, $team)
            && $user->canAccessDepartment($team, $department->id);
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
}
