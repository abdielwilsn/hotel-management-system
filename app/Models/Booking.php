<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'updated_by_user_id',
        'room_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'number_of_guests',
        'check_in_date',
        'check_out_date',
        'price_per_night',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'price_per_night' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    /**
     * @return HasMany<BookingDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(BookingDiscount::class);
    }

    /**
     * The current pending or approved discount (there is at most one).
     */
    public function activeDiscount(): HasOne
    {
        return $this->hasOne(BookingDiscount::class)
            ->whereIn('status', ['pending', 'approved'])
            ->latestOfMany();
    }

    /**
     * The approved discount that actually reduces the bill, if any.
     */
    public function approvedDiscount(): HasOne
    {
        return $this->hasOne(BookingDiscount::class)
            ->where('status', 'approved')
            ->latestOfMany();
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'checked_in']);
    }

    public function getNightsAttribute(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }
}
