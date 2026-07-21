<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ViewInvoices, $team);
    }

    public function view(User $user, Invoice $invoice, Team $team): bool
    {
        return $invoice->team_id === $team->id && $user->hasAbility(Ability::ViewInvoices, $team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManageInvoices, $team);
    }

    public function update(User $user, Invoice $invoice, Team $team): bool
    {
        return $invoice->team_id === $team->id && $user->hasAbility(Ability::ManageInvoices, $team);
    }

    public function delete(User $user, Invoice $invoice, Team $team): bool
    {
        return $invoice->team_id === $team->id && $user->hasAbility(Ability::ManageInvoices, $team);
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
