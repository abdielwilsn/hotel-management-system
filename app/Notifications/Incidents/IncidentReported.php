<?php

namespace App\Notifications\Incidents;

use App\Models\Incident;
use App\Notifications\TeamNotification;

class IncidentReported extends TeamNotification
{
    public function __construct(public Incident $incident)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, incident_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->incident->team_id,
            'message' => "New {$this->incident->severity->label()} incident: {$this->incident->title}.",
            'url' => route('incidents.index', $this->incident->team->slug),
            'incident_id' => $this->incident->id,
        ];
    }
}
