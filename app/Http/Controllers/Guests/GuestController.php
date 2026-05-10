<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\SaveGuestRequest;
use App\Models\Guest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Guest::class, $current_team]);

        $guests = $current_team->guests()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('guests/Index', [
            'guests' => $guests,
            'tiers' => ['standard', 'silver', 'gold', 'platinum'],
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
