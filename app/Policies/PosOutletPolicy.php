<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\PosOutlet;
use App\Models\Team;
use App\Models\User;

class PosOutletPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
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
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, PosOutlet $outlet, Team $team): bool
    {
        return $outlet->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, PosOutlet $outlet, Team $team): bool
    {
        return $outlet->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
