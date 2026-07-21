<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\PosOutlet;
use App\Models\Team;
use App\Models\User;

class PosOutletPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::OperatePos, $team);
    }

    /**
     * Operate the point of sale for this outlet (take orders, print, view its reports).
     */
    public function operate(User $user, PosOutlet $outlet, Team $team): bool
    {
        return $outlet->team_id === $team->id && $user->canAccessPosOutlet($outlet);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManagePos, $team);
    }

    public function update(User $user, PosOutlet $outlet, Team $team): bool
    {
        return $outlet->team_id === $team->id
            && $user->hasAbility(Ability::ManagePos, $team)
            && $user->canAccessDepartment($team, $outlet->department_id);
    }

    public function delete(User $user, PosOutlet $outlet, Team $team): bool
    {
        return $outlet->team_id === $team->id
            && $user->hasAbility(Ability::ManagePos, $team)
            && $user->canAccessDepartment($team, $outlet->department_id);
    }
}
