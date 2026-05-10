<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    /** @use HasFactory<\Database\Factories\GuestFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'loyalty_tier',
        'loyalty_points',
        'last_stay_date',
        'preferences',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'last_stay_date' => 'date',
        'loyalty_points' => 'integer',
    ];

    protected $appends = ['full_name'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
