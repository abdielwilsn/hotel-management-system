<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'inventory_item_id',
        'source_external_id',
        'business_date',
        'opening_stock',
        'new_stock',
        'total_stock',
        'sales_qty',
        'closing_stock',
        'damaged',
        'shortage',
        'excess',
        'sales_value',
        'closing_value',
        'recorded_by',
        'notes',
        'is_closed',
    ];

    protected $casts = [
        'business_date' => 'date',
        'sales_value' => 'decimal:2',
        'closing_value' => 'decimal:2',
        'is_closed' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }
}
