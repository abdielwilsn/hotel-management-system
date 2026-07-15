<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\SavePosOutletRequest;
use App\Models\Department;
use App\Models\PosOutlet;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosOutletController extends Controller
{
    /**
     * Admin management screen: outlets and their assigned POS staff.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('create', [PosOutlet::class, $current_team]);

        $outlets = PosOutlet::query()
            ->forTeam($current_team)
            ->withCount(['menuItems', 'categories'])
            ->with('staff:id,name,email')
            ->orderBy('name')
            ->get();

        $members = $current_team->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role->value,
            ]);

        return Inertia::render('pos/Manage', [
            'outlets' => $outlets,
            'members' => $members,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SavePosOutletRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [PosOutlet::class, $current_team]);

        $outlet = PosOutlet::query()->create($request->payload($current_team));
        $this->syncOutletDepartment($current_team, $outlet);

        return redirect()->route('pos.manage', $current_team->slug)
            ->with('message', "Outlet {$outlet->name} has been created.");
    }

    public function update(SavePosOutletRequest $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);

        Gate::authorize('update', [$pos_outlet, $current_team]);

        $pos_outlet->update($request->payload($current_team));
        $this->syncOutletDepartment($current_team, $pos_outlet);

        return redirect()->route('pos.manage', $current_team->slug)
            ->with('message', "Outlet {$pos_outlet->name} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);

        Gate::authorize('delete', [$pos_outlet, $current_team]);

        $name = $pos_outlet->name;
        $pos_outlet->delete();

        return redirect()->route('pos.manage', $current_team->slug)
            ->with('message', "Outlet {$name} has been removed.");
    }

    /**
     * Assign a team member (typically a POS-staff user) to operate this outlet.
     */
    public function assignStaff(Request $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);

        Gate::authorize('update', [$pos_outlet, $current_team]);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('team_members', 'user_id')
                    ->where(fn ($query) => $query->where('team_id', $current_team->id)),
            ],
        ]);

        $pos_outlet->staff()->syncWithoutDetaching([
            (int) $validated['user_id'] => ['team_id' => $current_team->id],
        ]);

        $user = User::query()->findOrFail((int) $validated['user_id']);
        $this->registerStaffMember($current_team, $pos_outlet, $user);

        return redirect()->route('pos.manage', $current_team->slug)
            ->with('message', 'Staff member assigned to outlet.');
    }

    public function unassignStaff(Request $request, Team $current_team, PosOutlet $pos_outlet, User $user): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);

        Gate::authorize('update', [$pos_outlet, $current_team]);

        $pos_outlet->staff()->detach($user->id);

        return redirect()->route('pos.manage', $current_team->slug)
            ->with('message', 'Staff member removed from outlet.');
    }

    private function outletForTeam(Team $team, PosOutlet $outlet): void
    {
        if ($outlet->team_id !== $team->id) {
            abort(403);
        }
    }

    /**
     * Ensure the outlet is backed by a matching Department so it appears in the
     * Departments directory, and keep the department name in step with the outlet.
     */
    private function syncOutletDepartment(Team $team, PosOutlet $outlet): Department
    {
        $department = $outlet->department_id
            ? Department::query()->find($outlet->department_id)
            : null;

        if ($department) {
            if ($department->name !== $outlet->name) {
                $department->update(['name' => $outlet->name]);
            }
        } else {
            $department = Department::query()->firstOrCreate(
                ['team_id' => $team->id, 'name' => $outlet->name],
                ['description' => ucfirst($outlet->type).' point of sale', 'status' => 'active'],
            );

            $outlet->update(['department_id' => $department->id]);
        }

        return $department;
    }

    /**
     * Register an assigned POS user in the Staff directory under the outlet's
     * department, so bar/restaurant staff appear alongside other employees.
     */
    private function registerStaffMember(Team $team, PosOutlet $outlet, User $user): void
    {
        $department = $this->syncOutletDepartment($team, $outlet);

        Staff::query()->firstOrCreate(
            ['team_id' => $team->id, 'email' => $user->email],
            [
                'department_id' => $department->id,
                'full_name' => $user->name,
                'role' => $this->staffRoleForOutlet($outlet),
                'employment_date' => Carbon::today()->toDateString(),
                'status' => 'active',
            ],
        );
    }

    private function staffRoleForOutlet(PosOutlet $outlet): string
    {
        return $outlet->type === 'bar' ? 'bartender' : 'waiter';
    }
}
