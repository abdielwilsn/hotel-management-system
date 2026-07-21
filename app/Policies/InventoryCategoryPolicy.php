<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\InventoryCategory;
use App\Models\Team;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ViewInventory, $team);
    }

    public function view(User $user, InventoryCategory $inventoryCategory, Team $team): bool
    {
        return $inventoryCategory->team_id === $team->id && $user->hasAbility(Ability::ViewInventory, $team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManageInventory, $team);
    }

    public function update(User $user, InventoryCategory $inventoryCategory, Team $team): bool
    {
        return $inventoryCategory->team_id === $team->id && $user->hasAbility(Ability::ManageInventory, $team);
    }

    public function delete(User $user, InventoryCategory $inventoryCategory, Team $team): bool
    {
        return $inventoryCategory->team_id === $team->id && $user->hasAbility(Ability::ManageInventory, $team);
    }
}
