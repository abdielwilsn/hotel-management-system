<?php

namespace App\Models;

use Database\Factories\PosStockRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosStockRecord extends Model
{
    /** @use HasFactory<PosStockRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'pos_outlet_id',
        'pos_menu_item_id',
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
