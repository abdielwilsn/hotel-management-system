<?php

namespace App\Models;

use Database\Factories\BookingDiscountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDiscount extends Model
{
    /** @use HasFactory<BookingDiscountFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'booking_id',
        'type',
        'value',
        'amount',
        'reason',
        'status',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * The discount value in currency for a given subtotal.
     *
     * Percentage discounts are computed against the subtotal; fixed discounts
     * are capped at the subtotal so a bill can never go negative.
     */
    public function computeAmount(float $subtotal): float
    {
        if ($this->type === 'percentage') {
            return round($subtotal * (float) $this->value / 100, 2);
        }

        return round(min((float) $this->value, $subtotal), 2);
    }
}
