<?php

namespace App\Http\Controllers\Forecasts;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Support\ForecastService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function index(Request $request, Team $current_team, ForecastService $forecasts): Response
    {
        abort_unless(
            $request->user()?->hasAbility(Ability::ViewForecasts, $current_team),
            403,
        );

        $result = $forecasts->compute($current_team);

        return Inertia::render('forecasts/Index', [
            'forecast' => $result['forecast'],
            'alerts' => $result['alerts'],
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }
}
