<?php

namespace App\Notifications\Forecasts;

use App\Models\Team;
use App\Notifications\TeamNotification;

class ForecastDigest extends TeamNotification
{
    /**
     * @param  array<int, array{level: string, title: string, message: string}>  $alerts
     */
    public function __construct(public Team $team, public array $alerts)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, alerts: array<int, array{level: string, title: string, message: string}>}
     */
    public function toArray(object $notifiable): array
    {
        $titles = collect($this->alerts)->pluck('title')->implode(', ');

        return [
            'team_id' => $this->team->id,
            'message' => "Forecast risk: {$titles}.",
            'url' => route('forecasts.index', $this->team->slug),
            'alerts' => $this->alerts,
        ];
    }
}
