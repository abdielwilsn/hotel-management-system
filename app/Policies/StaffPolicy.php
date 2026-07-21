<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ViewStaff, $team);
    }

    public function view(User $user, Staff $staff, Team $team): bool
    {
        return $staff->team_id === $team->id
            && $user->hasAbility(Ability::ViewStaff, $team)
            && $user->canAccessDepartment($team, $staff->department_id);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManageStaff, $team);
    }

    public function update(User $user, Staff $staff, Team $team): bool
    {
        return $staff->team_id === $team->id
            && $user->hasAbility(Ability::ManageStaff, $team)
            && $user->canAccessDepartment($team, $staff->department_id);
    }

    public function delete(User $user, Staff $staff, Team $team): bool
    {
        return $staff->team_id === $team->id
            && $user->hasAbility(Ability::ManageStaff, $team)
            && $user->canAccessDepartment($team, $staff->department_id);
    }

    public function restore(User $user, Staff $staff): bool
    {
        return false;
    }

    public function forceDelete(User $user, Staff $staff): bool
    {
        return false;
    }
}
