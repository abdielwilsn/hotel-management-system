<?php

namespace App\Http\Responses;

use App\Enums\Ability;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
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

        if (! $user->hasAbility(Ability::AccessHotel, $team)) {
            return redirect()->to("/{$team->slug}/pos");
        }

        return redirect()->intended("/{$team->slug}".Fortify::redirects('login'));
    }
}
