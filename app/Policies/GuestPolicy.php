<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Guest;
use App\Models\Team;
use App\Models\User;

class GuestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Guest $guest, Team $team): bool
    {
        return $guest->team_id === $team->id && $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Guest $guest, Team $team): bool
    {
        return $guest->team_id === $team->id && $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Guest $guest, Team $team): bool
    {
        return $guest->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Guest $guest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Guest $guest): bool
    {
        return false;
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
