<?php

namespace App\Console\Commands;

use App\Enums\Ability;
use App\Models\Team;
use App\Notifications\Forecasts\ForecastDigest;
use App\Support\ForecastService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * The Forecasts page already computes occupancy/collection/profitability
 * risk alerts — but only whoever remembers to visit that page ever sees
 * them. This pushes the same alerts out instead.
 */
class SendForecastDigest extends Command
{
    protected $signature = 'forecasts:daily-digest';

    protected $description = 'Send a digest of forecast risk alerts to team owners/managers';

    public function handle(ForecastService $forecasts): int
    {
        $sent = 0;

        Team::query()->has('rooms')->each(function (Team $team) use ($forecasts, &$sent): void {
            $alerts = $forecasts->alertsFor($team);

            if (empty($alerts)) {
                return;
            }

            Notification::send(
                $team->membersWithAbility(Ability::ViewForecasts),
                new ForecastDigest($team, $alerts),
            );

            $sent++;
        });

        $this->info($sent > 0
            ? "Sent forecast digests for {$sent} team(s)."
            : 'No teams have forecast risk alerts today.');

        return self::SUCCESS;
    }
}
