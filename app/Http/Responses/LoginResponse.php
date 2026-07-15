<?php

namespace App\Http\Responses;

use App\Enums\TeamRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->personalTeam();

        if (! $team) {
            abort(403);
        }

        URL::defaults(['current_team' => $team->slug]);

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        // POS-only staff cannot reach the dashboard, so send them straight to
        // their point of sale instead of honouring a forbidden intended URL.
        $role = $user->teamRole($team);

        if ($role !== null && ! $role->isAtLeast(TeamRole::Member)) {
            return redirect()->to("/{$team->slug}/pos");
        }

        return redirect()->intended("/{$team->slug}".Fortify::redirects('login'));
    }
}
