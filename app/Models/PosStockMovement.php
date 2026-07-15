<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosStockMovement extends Model
{
    protected $fillable = [
        'team_id',
        'pos_outlet_id',
        'pos_menu_item_id',
        'pos_order_id',
        'type',
        'quantity',
        'balance_after',
        'unit_cost',
        'supplier',
        'reference',
        'recorded_by',
        'notes',
        'business_date',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'business_date' => 'date',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(PosOutlet::class, 'pos_outlet_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(PosMenuItem::class, 'pos_menu_item_id');
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }
}
