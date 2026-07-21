<?php

namespace App\Http\Middleware;

use App\Enums\Ability;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $team] = [$request->user(), $this->team($request)];

        abort_if(! $user || ! $team || ! $user->belongsToTeam($team), 403);

        $this->ensureTeamMemberHasRequiredAccess($user, $team, $minimumRole);

        if ($request->route('current_team') && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $next($request);
    }

    /**
     * Ensure the user may reach this group of routes.
     *
     * Route groups ask for a coarse level of access rather than a specific role,
     * so that a manager can grant a POS-only user the run of the hotel modules
     * by editing their role instead of needing a code change here.
     */
    protected function ensureTeamMemberHasRequiredAccess(User $user, Team $team, ?string $requiredAccess): void
    {
        if ($requiredAccess === null) {
            return;
        }

        $ability = match ($requiredAccess) {
            'member' => Ability::AccessHotel,
            default => Ability::tryFrom($requiredAccess),
        };

        abort_if($ability === null || ! $user->hasAbility($ability, $team), 403);
    }

    /**
     * Get the team associated with the request.
     */
    protected function team(Request $request): ?Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (is_string($team)) {
            $team = Team::where('slug', $team)->first();
        }

        return $team;
    }
}
