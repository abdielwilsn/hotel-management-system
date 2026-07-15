<?php

namespace App\Models;

use Database\Factories\PosMenuItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosMenuItem extends Model
{
    /** @use HasFactory<PosMenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'pos_outlet_id',
        'pos_category_id',
        'name',
        'price',
        'unit',
        'track_stock',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'track_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(PosOutlet::class, 'pos_outlet_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PosCategory::class, 'pos_category_id');
    }

    /**
     * @return HasMany<PosStockRecord, $this>
     */
    public function stockRecords(): HasMany
    {
        return $this->hasMany(PosStockRecord::class);
    }

    /**
     * @return HasMany<PosStockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(PosStockMovement::class);
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
