<?php

namespace App\Http\Controllers\Incidents;

use App\Enums\Ability;
use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\ResolveIncidentRequest;
use App\Http\Requests\Incidents\SaveIncidentRequest;
use App\Models\Department;
use App\Models\Incident;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    /**
     * The incident log, limited to the departments this user works in.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Incident::class, $current_team]);

        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer'],
            'severity' => ['nullable', 'string'],
        ]);

        $incidents = Incident::query()
            ->forTeam($current_team)
            ->visibleTo($user, $current_team)
            ->with(['department:id,name', 'reportedBy:id,name', 'resolvedBy:id,name', 'room:id,room_number'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->mostPressing()
            ->limit(200)
            ->get()
            ->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'title' => $incident->title,
                'description' => $incident->description,
                'category' => $incident->category->value,
                'category_label' => $incident->category->label(),
                'severity' => $incident->severity->value,
                'severity_label' => $incident->severity->label(),
                'status' => $incident->status->value,
                'status_label' => $incident->status->label(),
                'is_open' => $incident->isOpen(),
                'occurred_at' => $incident->occurred_at->toIso8601String(),
                'department' => $incident->department?->only('id', 'name'),
                'room_number' => $incident->room?->room_number,
                'reported_by' => $incident->reportedBy?->name,
                'resolved_by' => $incident->resolvedBy?->name,
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'resolution_notes' => $incident->resolution_notes,
            ]);

        return Inertia::render('incidents/Index', [
            'incidents' => $incidents,
            // Somebody can only file against a department they actually work in.
            'departments' => $this->reportableDepartments($user, $current_team),
            'categories' => IncidentCategory::options(),
            'severities' => IncidentSeverity::options(),
            'statuses' => IncidentStatus::options(),
            'filters' => $filters,
            'canReport' => $user->hasAbility(Ability::ReportIncidents, $current_team),
            'canResolve' => $user->hasAbility(Ability::ResolveIncidents, $current_team),
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    /**
     * File an incident.
     */
    public function store(SaveIncidentRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Incident::class, $current_team]);

        $data = $request->validated();

        // The department list in the form is already narrowed; this is the
        // check that actually holds, since the form can be replayed.
        abort_unless(
            $request->user()->canAccessDepartment($current_team, (int) $data['department_id']),
            403,
        );

        $current_team->incidents()->create([
            ...$data,
            'status' => IncidentStatus::Open,
            'reported_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Incident reported.')]);

        return to_route('incidents.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Move an incident along, and close it out when it is done.
     */
    public function resolve(ResolveIncidentRequest $request, Team $current_team, Incident $incident): RedirectResponse
    {
        $incident = $this->incidentForTeam($current_team, $incident);

        Gate::authorize('resolve', [$incident, $current_team]);

        $status = IncidentStatus::from($request->validated('status'));

        $incident->update([
            'status' => $status,
            'resolution_notes' => $request->validated('resolution_notes'),
            // Reopening clears the sign-off, so a closed incident always says
            // who closed it and when.
            'resolved_by_user_id' => $status->isOpen() ? null : $request->user()->id,
            'resolved_at' => $status->isOpen() ? null : now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Incident updated.')]);

        return to_route('incidents.index', ['current_team' => $current_team->slug]);
    }

    /**
     * The departments this user may file an incident against.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function reportableDepartments(User $user, Team $team)
    {
        return Department::query()
            ->forTeam($team)
            ->visibleTo($user, $team)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department) => $department->only('id', 'name'));
    }

    /**
     * Ensure the incident belongs to the given team.
     */
    private function incidentForTeam(Team $team, Incident $incident): Incident
    {
        abort_unless($incident->team_id === $team->id, 404);

        return $incident;
    }
}
