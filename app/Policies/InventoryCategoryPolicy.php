<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\InventoryCategory;
use App\Models\Team;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, InventoryCategory $inventoryCategory, Team $team): bool
    {
        return $inventoryCategory->team_id === $team->id && $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, InventoryCategory $inventoryCategory, Team $team): bool
    {
        return $inventoryCategory->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, InventoryCategory $inventoryCategory, Team $team): bool
    {
        return $inventoryCategory->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        return $user->teamRole($team)->isAtLeast(TeamRole::Admin);
    }
}
