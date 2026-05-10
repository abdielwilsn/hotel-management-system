<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Staff $staff, Team $team): bool
    {
        return $staff->team_id === $team->id && $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, Staff $staff, Team $team): bool
    {
        return $staff->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, Staff $staff, Team $team): bool
    {
        return $staff->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function restore(User $user, Staff $staff): bool
    {
        return false;
    }

    public function forceDelete(User $user, Staff $staff): bool
    {
        return false;
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        $role = $user->teamRole($team);

        return $role->isAtLeast(TeamRole::Admin);
    }
}
