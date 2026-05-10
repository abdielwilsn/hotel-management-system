<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;

class ExpensePolicy
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
    public function view(User $user, Expense $expense, Team $team): bool
    {
        return $expense->team_id === $team->id && $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Expense $expense, Team $team): bool
    {
        return $expense->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Expense $expense, Team $team): bool
    {
        return $expense->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return false;
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        return $user->teamRole($team)->isAtLeast(TeamRole::Admin);
    }
}
