<?php

namespace App\Http\Controllers\Teams;

use App\Enums\Ability;
use App\Enums\DataScope;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateMemberAccessRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $newRole = TeamRole::from($request->validated('role'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->update(['role' => $newRole]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Put a member into their departments, and say how much they may see.
     *
     * The departments are what grant permission, so they are kept whatever the
     * data scope is: somebody can work in Finance and still be trusted to look
     * at records across the whole hotel.
     */
    public function updateAccess(UpdateMemberAccessRequest $request, Team $team, User $user): RedirectResponse
    {
        // Governed by the manage-permissions ability rather than the owner-only
        // updateMember permission, so a manager can run access day to day.
        abort_unless($request->user()->hasAbility(Ability::ManagePermissions, $team), 403);

        // The owner's access is not negotiable, otherwise a team could be left
        // with nobody able to administer it.
        abort_if($team->owner()?->is($user) ?? false, 403, __('The team owner\'s access cannot be changed.'));

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $scope = DataScope::from($request->validated('data_scope'));

        DB::transaction(function () use ($membership, $request, $scope, $team, $user): void {
            $membership->update(['data_scope' => $scope]);

            $user->departments()->wherePivot('team_id', $team->id)->detach();

            $user->departments()->attach(
                collect($request->validated('department_ids'))->mapWithKeys(
                    fn ($id) => [$id => ['team_id' => $team->id]]
                )->all()
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member access updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
