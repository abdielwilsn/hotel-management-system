<?php

namespace App\Models;

use App\Support\StayCalculator;
use App\Support\StayCharge;
use App\Support\StayPolicy;
use Carbon\CarbonImmutable;
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
        'check_in_at',
        'check_out_at',
        'checked_out_at',
        'chargeable_nights',
        'nights_basis',
        'price_per_night',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'notified_needs_settlement_at' => 'datetime',
        'chargeable_nights' => 'integer',
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
     * @return HasMany<PosOrder, $this>
     */
    public function posOrders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
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

    /**
     * Whether the guest left before the departure they booked.
     *
     * Leaving early does not by itself change the bill: the room was held for
     * the whole stay. It does mean somebody should decide whether to charge for
     * the unused nights, which is what the stay adjustment flow is for.
     */
    public function departedEarly(): bool
    {
        if ($this->checked_out_at === null || $this->check_out_at === null) {
            return false;
        }

        return $this->checked_out_at->lessThan($this->check_out_at);
    }

    /**
     * Nights the guest paid for but did not use, as whole nights.
     */
    public function unusedNights(): int
    {
        if (! $this->departedEarly()) {
            return 0;
        }

        return (int) $this->checked_out_at
            ->startOfDay()
            ->diffInDays($this->check_out_at->startOfDay());
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'checked_in']);
    }

    /**
     * Bookings that block the given date range.
     *
     * A stay occupies [check_in_date, check_out_date): the checkout day is free
     * for the next guest, so two stays only clash when the ranges genuinely
     * overlap. This is the single source of truth shared by booking validation
     * and the room availability lookup, so the two can never disagree.
     */
    public function scopeOverlapping(Builder $query, string $checkInDate, string $checkOutDate): Builder
    {
        // whereDate (not where) because the columns are stored as datetimes —
        // a raw string compare would read "2026-06-05 00:00:00" > "2026-06-05".
        return $query->active()
            ->whereDate('check_in_date', '<', $checkOutDate)
            ->whereDate('check_out_date', '>', $checkInDate);
    }

    /**
     * @return HasMany<BookingStayAdjustment, $this>
     */
    public function stayAdjustments(): HasMany
    {
        return $this->hasMany(BookingStayAdjustment::class);
    }

    /**
     * The approved override of the computed nights, if a manager granted one.
     */
    public function approvedStayAdjustment(): ?BookingStayAdjustment
    {
        return $this->stayAdjustments()
            ->approved()
            ->latest('reviewed_at')
            ->first();
    }

    /**
     * The outstanding night-count request, eager-loadable for the bookings list.
     */
    public function activeStayAdjustment(): HasOne
    {
        return $this->hasOne(BookingStayAdjustment::class)
            ->where('status', 'pending')
            ->latestOfMany();
    }

    /**
     * The pending request for a different night count, if the desk raised one.
     */
    public function pendingStayAdjustment(): ?BookingStayAdjustment
    {
        return $this->stayAdjustments()->pending()->latest()->first();
    }

    /**
     * When the guest arrives, falling back to the house check-in time.
     *
     * Bookings taken before the clock mattered only have dates, so the standard
     * arrival time stands in for them.
     */
    public function arrivesAt(StayPolicy $policy): CarbonImmutable
    {
        if ($this->check_in_at !== null) {
            return CarbonImmutable::instance($this->check_in_at->toDateTime());
        }

        return $policy->checkInBoundaryOn($this->check_in_date);
    }

    /**
     * When the guest leaves, falling back to the house checkout time.
     */
    public function departsAt(StayPolicy $policy): CarbonImmutable
    {
        if ($this->check_out_at !== null) {
            return CarbonImmutable::instance($this->check_out_at->toDateTime());
        }

        return $policy->checkOutBoundaryOn($this->check_out_date);
    }

    /**
     * What this stay works out to under the given policy, before any approved
     * override is taken into account.
     */
    public function computedStayCharge(StayPolicy $policy): StayCharge
    {
        return (new StayCalculator($policy))->charge(
            $this->arrivesAt($policy),
            $this->departsAt($policy),
        );
    }

    /**
     * The nights this booking is billed for.
     *
     * Prefers what was worked out and stored when the booking was written, so a
     * bill never silently changes underneath a guest because the house clock was
     * edited afterwards.
     */
    public function getNightsAttribute(): int
    {
        if ($this->chargeable_nights !== null) {
            return $this->chargeable_nights;
        }

        return max(1, (int) $this->check_in_date->diffInDays($this->check_out_date));
    }
}
