<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Room $room, Team $team): bool
    {
        return $room->team_id === $team->id && $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, Room $room, Team $team): bool
    {
        return $room->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, Room $room, Team $team): bool
    {
        return $room->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function restore(User $user, Room $room): bool
    {
        return false;
    }

    public function forceDelete(User $user, Room $room): bool
    {
        return false;
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        $role = $user->teamRole($team);

        return $role->isAtLeast(TeamRole::Admin);
    }
}
