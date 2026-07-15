<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'booking_id',
        'invoice_number',
        'guest_name',
        'guest_email',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'partially_paid', 'overdue']);
    }

    public function getBalanceDueAttribute(): float
    {
        return max((float) $this->total_amount - (float) $this->paid_amount, 0);
    }

    /**
     * Derive the invoice status from a paid amount against the current total.
     * A voided invoice keeps its status.
     */
    public function statusFor(float $paidAmount): string
    {
        $total = (float) $this->total_amount;

        if ($this->status === 'void') {
            return 'void';
        }

        if ($paidAmount >= $total && $total > 0) {
            return 'paid';
        }

        if ($paidAmount > 0 && $paidAmount < $total) {
            return 'partially_paid';
        }

        return 'issued';
    }
}
