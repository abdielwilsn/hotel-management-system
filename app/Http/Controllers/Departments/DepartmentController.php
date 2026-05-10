<?php

namespace App\Http\Controllers\Departments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\SaveDepartmentRequest;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Department::class, $current_team]);

        return Inertia::render('departments/Index', [
            'departments' => Department::query()
                ->forTeam($current_team)
                ->with('manager:id,name,email')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'status' => $department->status,
                    'manager' => $department->manager
                        ? [
                            'id' => $department->manager->id,
                            'name' => $department->manager->name,
                            'email' => $department->manager->email,
                        ]
                        : null,
                ]),
            'members' => $current_team->members()
                ->select('users.id', 'users.name', 'users.email')
                ->orderBy('users.name')
                ->get()
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ]),
            'team' => [
                'id' => $current_team->id,
                'slug' => $current_team->slug,
                'name' => $current_team->name,
            ],
            'statuses' => ['active', 'inactive'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(SaveDepartmentRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Department::class, $current_team]);

        Department::create([
            ...$request->validated(),
            'team_id' => $current_team->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return to_route('departments.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Team $current_team, Department $department): Response
    {
        $department = $this->departmentForTeam($current_team, $department);

        Gate::authorize('view', [$department, $current_team]);

        return Inertia::render('departments/Edit', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
                'status' => $department->status,
                'manager_id' => $department->manager_id,
            ],
            'members' => $current_team->members()
                ->select('users.id', 'users.name', 'users.email')
                ->orderBy('users.name')
                ->get()
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ]),
            'team' => [
                'id' => $current_team->id,
                'slug' => $current_team->slug,
                'name' => $current_team->name,
            ],
            'statuses' => ['active', 'inactive'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SaveDepartmentRequest $request, Team $current_team, Department $department): RedirectResponse
    {
        $department = $this->departmentForTeam($current_team, $department);

        Gate::authorize('update', [$department, $current_team]);

        $department->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department updated.')]);

        return to_route('departments.edit', [
            'current_team' => $current_team->slug,
            'department' => $department->id,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Team $current_team, Department $department): RedirectResponse
    {
        $department = $this->departmentForTeam($current_team, $department);

        Gate::authorize('delete', [$department, $current_team]);

        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return to_route('departments.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Ensure the department belongs to the given team.
     */
    protected function departmentForTeam(Team $team, Department $department): Department
    {
        abort_unless($department->team_id === $team->id, 404);

        return $department;
    }
}
