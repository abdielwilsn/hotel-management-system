<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ViewRooms, $team);
    }

    public function view(User $user, Room $room, Team $team): bool
    {
        return $room->team_id === $team->id && $user->hasAbility(Ability::ViewRooms, $team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManageRooms, $team);
    }

    public function update(User $user, Room $room, Team $team): bool
    {
        return $room->team_id === $team->id && $user->hasAbility(Ability::ManageRooms, $team);
    }

    public function delete(User $user, Room $room, Team $team): bool
    {
        return $room->team_id === $team->id && $user->hasAbility(Ability::ManageRooms, $team);
    }

    public function restore(User $user, Room $room): bool
    {
        return false;
    }

    public function forceDelete(User $user, Room $room): bool
    {
        return false;
    }
}
