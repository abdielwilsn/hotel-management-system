<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\PosMenuItem;
use App\Models\Team;
use App\Models\User;

class PosMenuItemPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::OperatePos, $team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManagePos, $team);
    }

    public function update(User $user, PosMenuItem $item, Team $team): bool
    {
        return $item->team_id === $team->id && $user->hasAbility(Ability::ManagePos, $team);
    }

    public function delete(User $user, PosMenuItem $item, Team $team): bool
    {
        return $item->team_id === $team->id && $user->hasAbility(Ability::ManagePos, $team);
    }
}
