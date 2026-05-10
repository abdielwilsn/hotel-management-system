<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Invoice $invoice, Team $team): bool
    {
        return $invoice->team_id === $team->id && $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->hasAdminPrivileges($user, $team);
    }

    public function update(User $user, Invoice $invoice, Team $team): bool
    {
        return $invoice->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function delete(User $user, Invoice $invoice, Team $team): bool
    {
        return $invoice->team_id === $team->id && $this->hasAdminPrivileges($user, $team);
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    private function hasAdminPrivileges(User $user, Team $team): bool
    {
        $role = $user->teamRole($team);

        return $role->isAtLeast(TeamRole::Admin);
    }
}
