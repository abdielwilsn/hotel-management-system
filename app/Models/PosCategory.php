<?php

namespace App\Models;

use Database\Factories\PosCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCategory extends Model
{
    /** @use HasFactory<PosCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'pos_outlet_id',
        'name',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(PosOutlet::class, 'pos_outlet_id');
    }

    /**
     * @return HasMany<PosMenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(PosMenuItem::class);
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }
}
