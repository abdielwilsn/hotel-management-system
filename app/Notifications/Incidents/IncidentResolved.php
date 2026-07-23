<?php

namespace App\Notifications\Incidents;

use App\Models\Incident;
use App\Notifications\TeamNotification;

class IncidentResolved extends TeamNotification
{
    public function __construct(public Incident $incident)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, incident_id: int, status: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->incident->team_id,
            'message' => "Your incident \"{$this->incident->title}\" was marked {$this->incident->status->label()}.",
            'url' => route('incidents.index', $this->incident->team->slug),
            'incident_id' => $this->incident->id,
            'status' => $this->incident->status->value,
        ];
    }
}
