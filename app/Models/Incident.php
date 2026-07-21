<?php

namespace App\Models;

use App\Concerns\BelongsToDepartment;
use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that went wrong, recorded against the department it happened in.
 *
 * Filing against a department rather than the hotel as a whole is what lets
 * housekeeping see housekeeping's incidents without seeing the bar's, using the
 * same department scope that governs everything else.
 */
#[Fillable([
    'team_id',
    'department_id',
    'title',
    'description',
    'category',
    'severity',
    'status',
    'occurred_at',
    'room_id',
    'booking_id',
    'reported_by_user_id',
    'resolved_by_user_id',
    'resolved_at',
    'resolution_notes',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use BelongsToDepartment, HasFactory;

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->id);
    }

    /**
     * Incidents that still need somebody to do something.
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            IncidentStatus::Open->value,
            IncidentStatus::Investigating->value,
        ]);
    }

    /**
     * Worst and newest first, which is the order they should be worked in.
     */
    public function scopeMostPressing(Builder $query): void
    {
        $query
            ->orderByRaw("CASE severity
                WHEN 'critical' THEN 4
                WHEN 'high' THEN 3
                WHEN 'medium' THEN 2
                ELSE 1 END DESC")
            ->orderByDesc('occurred_at');
    }

    /**
     * Whether this incident is still outstanding.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'category' => IncidentCategory::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
        ];
    }
}
