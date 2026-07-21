<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\PosCategory;
use App\Models\Team;
use App\Models\User;

class PosCategoryPolicy
{
    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManagePos, $team);
    }

    public function update(User $user, PosCategory $category, Team $team): bool
    {
        return $category->team_id === $team->id && $user->hasAbility(Ability::ManagePos, $team);
    }

    public function delete(User $user, PosCategory $category, Team $team): bool
    {
        return $category->team_id === $team->id && $user->hasAbility(Ability::ManagePos, $team);
    }
}
