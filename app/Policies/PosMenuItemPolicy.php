<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\PosMenuItem;
use App\Models\Team;
use App\Models\User;

class PosMenuItemPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, PosMenuItem $item, Team $team): bool
    {
        return $item->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, PosMenuItem $item, Team $team): bool
    {
        return $item->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
