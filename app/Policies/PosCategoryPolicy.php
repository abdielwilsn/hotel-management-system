<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\PosCategory;
use App\Models\Team;
use App\Models\User;

class PosCategoryPolicy
{
    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, PosCategory $category, Team $team): bool
    {
        return $category->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, PosCategory $category, Team $team): bool
    {
        return $category->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
