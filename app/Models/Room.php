<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'room_number',
        'floor',
        'room_type',
        'capacity',
        'price_per_night',
        'status',
        'description',
        'features',
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
        'features' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('room_type', $type);
    }
}
