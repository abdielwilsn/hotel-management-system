<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Booking $booking, Team $team): bool
    {
        return $booking->team_id === $team->id && $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function update(User $user, Booking $booking, Team $team): bool
    {
        return $booking->team_id === $team->id && $user->belongsToTeam($team);
    }

    public function delete(User $user, Booking $booking, Team $team): bool
    {
        return $booking->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function restore(User $user, Booking $booking): bool
    {
        return false;
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        return false;
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        $role = $user->teamRole($team);

        return $role?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
