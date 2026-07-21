<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ViewInventory, $team);
    }

    public function view(User $user, InventoryItem $inventoryItem, Team $team): bool
    {
        return $inventoryItem->team_id === $team->id && $user->hasAbility(Ability::ViewInventory, $team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManageInventory, $team);
    }

    public function update(User $user, InventoryItem $inventoryItem, Team $team): bool
    {
        return $inventoryItem->team_id === $team->id && $user->hasAbility(Ability::ManageInventory, $team);
    }

    public function delete(User $user, InventoryItem $inventoryItem, Team $team): bool
    {
        return $inventoryItem->team_id === $team->id && $user->hasAbility(Ability::ManageInventory, $team);
    }
}
