<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Incident;
use App\Models\Team;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ViewIncidents, $team);
    }

    public function view(User $user, Incident $incident, Team $team): bool
    {
        return $incident->team_id === $team->id
            && $user->hasAbility(Ability::ViewIncidents, $team)
            && $user->canAccessDepartment($team, $incident->department_id);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ReportIncidents, $team);
    }

    /**
     * Correcting the record is the reporter's job while it is still open;
     * closing it out belongs to whoever can resolve.
     */
    public function update(User $user, Incident $incident, Team $team): bool
    {
        if ($incident->team_id !== $team->id) {
            return false;
        }

        if (! $user->canAccessDepartment($team, $incident->department_id)) {
            return false;
        }

        if ($user->hasAbility(Ability::ResolveIncidents, $team)) {
            return true;
        }

        return $incident->isOpen()
            && $incident->reported_by_user_id === $user->id
            && $user->hasAbility(Ability::ReportIncidents, $team);
    }

    public function resolve(User $user, Incident $incident, Team $team): bool
    {
        return $incident->team_id === $team->id
            && $user->hasAbility(Ability::ResolveIncidents, $team)
            && $user->canAccessDepartment($team, $incident->department_id);
    }

    public function delete(User $user, Incident $incident, Team $team): bool
    {
        return $incident->team_id === $team->id
            && $user->hasAbility(Ability::ResolveIncidents, $team)
            && $user->canAccessDepartment($team, $incident->department_id);
    }
}
