<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, InventoryItem $inventoryItem, Team $team): bool
    {
        return $inventoryItem->team_id === $team->id && $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, InventoryItem $inventoryItem, Team $team): bool
    {
        return $inventoryItem->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, InventoryItem $inventoryItem, Team $team): bool
    {
        return $inventoryItem->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        return $user->teamRole($team)->isAtLeast(TeamRole::Admin);
    }
}
