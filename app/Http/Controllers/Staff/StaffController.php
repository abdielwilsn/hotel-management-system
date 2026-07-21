<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staffs\SaveStaffRequest;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Staff::class, $current_team]);

        $staff = $current_team->staff()
            ->with('department')
            ->visibleTo($request->user(), $current_team)
            ->orderBy('full_name')
            ->get();

        $departments = $current_team->departments()
            ->visibleTo($request->user(), $current_team)
            ->get();
        $roles = ['receptionist', 'housekeeping', 'accountant', 'manager', 'admin'];
        $statuses = ['active', 'inactive', 'on_leave'];

        return Inertia::render('staff/Index', [
            'staff' => $staff,
            'departments' => $departments,
            'roles' => $roles,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveStaffRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Staff::class, $current_team]);

        [$staff, $message] = DB::transaction(function () use ($request, $current_team): array {
            $data = $request->validated();

            // The password belongs to the login account, not the staff record.
            $password = $data['password'] ?? null;
            unset($data['password']);

            // Create staff record
            $staff = $current_team->staff()->create($data);

            $user = User::query()->where('email', $staff->email)->first();
            $isNewUser = $user === null;

            if ($isNewUser) {
                $user = User::create([
                    'name' => $staff->full_name,
                    'email' => $staff->email,
                    // Without a chosen password we store an unguessable one and
                    // email a reset link instead.
                    'password' => Hash::make($password ?? bin2hex(random_bytes(16))),
                ]);
            }

            // Add user to the team if not already a member
            if (! $user->teams()->where('team_id', $current_team->id)->exists()) {
                $user->teams()->attach($current_team, [
                    'role' => $this->mapStaffRoleToTeamRole($data['role']),
                ]);
            }

            // Never reset the password of an account that already existed —
            // that would let a manager take over someone else's login.
            if (! $isNewUser) {
                return [$staff, "{$staff->full_name} has been added to staff and linked to the existing {$staff->email} account. Their current password is unchanged."];
            }

            if ($password !== null) {
                return [$staff, "{$staff->full_name} has been added to staff. Share the password you set with them so they can sign in as {$staff->email}."];
            }

            Password::broker()->sendResetLink(['email' => $user->email]);

            return [$staff, "{$staff->full_name} has been added to staff. A password setup link was sent to {$staff->email}."];
        });

        return redirect()->route('staff.index', $current_team->slug)
            ->with('message', $message);
    }

    public function edit(Request $request, Team $current_team, Staff $staff): Response
    {
        $this->staffForTeam($current_team, $staff);

        Gate::authorize('update', [$staff, $current_team]);

        $departments = $current_team->departments()->get();
        $roles = ['receptionist', 'housekeeping', 'accountant', 'manager', 'admin'];
        $statuses = ['active', 'inactive', 'on_leave'];

        return Inertia::render('staff/Edit', [
            'staff' => $staff,
            'departments' => $departments,
            'roles' => $roles,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SaveStaffRequest $request, Team $current_team, Staff $staff): RedirectResponse
    {
        $this->staffForTeam($current_team, $staff);

        Gate::authorize('update', [$staff, $current_team]);

        // password only applies when creating the login account.
        $staff->update(Arr::except($request->validated(), ['password']));

        return redirect()->route('staff.index', $current_team->slug)
            ->with('message', "{$staff->full_name} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, Staff $staff): RedirectResponse
    {
        $this->staffForTeam($current_team, $staff);

        Gate::authorize('delete', [$staff, $current_team]);

        $name = $staff->full_name;
        $staff->delete();

        return redirect()->route('staff.index', $current_team->slug)
            ->with('message', "{$name} has been removed from staff.");
    }

    private function staffForTeam(Team $team, Staff $staff): void
    {
        if ($staff->team_id !== $team->id) {
            abort(403);
        }
    }

    private function mapStaffRoleToTeamRole(string $staffRole): string
    {
        $roleMap = [
            'manager' => 'admin',
            'admin' => 'admin',
            'accountant' => 'member',
            'receptionist' => 'member',
            'housekeeping' => 'member',
        ];

        return $roleMap[$staffRole] ?? 'member';
    }
}
