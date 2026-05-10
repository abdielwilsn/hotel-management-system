<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\SaveGuestRequest;
use App\Models\Guest;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Guest::class, $current_team]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'loyalty_tier' => ['nullable', 'string', 'in:standard,silver,gold,platinum'],
            'last_stay_from' => ['nullable', 'date'],
            'last_stay_to' => ['nullable', 'date'],
            'min_loyalty_points' => ['nullable', 'integer', 'min:0'],
            'has_email' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $guests = $current_team->guests()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['loyalty_tier'] ?? null, function (Builder $query, string $tier): void {
                $query->where('loyalty_tier', $tier);
            })
            ->when($filters['last_stay_from'] ?? null, function (Builder $query, string $lastStayFrom): void {
                $query->whereDate('last_stay_date', '>=', $lastStayFrom);
            })
            ->when($filters['last_stay_to'] ?? null, function (Builder $query, string $lastStayTo): void {
                $query->whereDate('last_stay_date', '<=', $lastStayTo);
            })
            ->when($filters['min_loyalty_points'] ?? null, function (Builder $query, int $minimumPoints): void {
                $query->where('loyalty_points', '>=', $minimumPoints);
            })
            ->when($filters['has_email'] ?? null, function (Builder $query, string $hasEmail): void {
                if ($hasEmail === 'yes') {
                    $query->whereNotNull('email')->where('email', '!=', '');

                    return;
                }

                $query->where(function (Builder $subQuery): void {
                    $subQuery->whereNull('email')->orWhere('email', '');
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('guests/Index', [
            'guests' => $guests,
            'tiers' => ['standard', 'silver', 'gold', 'platinum'],
            'filters' => Arr::only($filters, [
                'search',
                'loyalty_tier',
                'last_stay_from',
                'last_stay_to',
                'min_loyalty_points',
                'has_email',
            ]),
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveGuestRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Guest::class, $current_team]);

        $guest = $current_team->guests()->create($request->payload($current_team));

        return redirect()->route('guests.index', $current_team->slug)
            ->with('message', "Guest {$guest->full_name} has been created.");
    }

    public function edit(Request $request, Team $current_team, Guest $guest): Response
    {
        $this->guestForTeam($current_team, $guest);

        Gate::authorize('update', [$guest, $current_team]);

        return Inertia::render('guests/Edit', [
            'guest' => $guest,
            'tiers' => ['standard', 'silver', 'gold', 'platinum'],
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SaveGuestRequest $request, Team $current_team, Guest $guest): RedirectResponse
    {
        $this->guestForTeam($current_team, $guest);

        Gate::authorize('update', [$guest, $current_team]);

        $guest->update($request->payload($current_team));

        return redirect()->route('guests.index', $current_team->slug)
            ->with('message', "Guest {$guest->full_name} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, Guest $guest): RedirectResponse
    {
        $this->guestForTeam($current_team, $guest);

        Gate::authorize('delete', [$guest, $current_team]);

        $name = $guest->full_name;
        $guest->delete();

        return redirect()->route('guests.index', $current_team->slug)
            ->with('message', "Guest {$name} has been removed.");
    }

    private function guestForTeam(Team $team, Guest $guest): void
    {
        if ($guest->team_id !== $team->id) {
            abort(403);
        }
    }
}
