<?php

namespace App\Models;

use Database\Factories\PosOutletFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOutlet extends Model
{
    /** @use HasFactory<PosOutletFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'department_id',
        'name',
        'type',
        'status',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<PosCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(PosCategory::class);
    }

    /**
     * @return HasMany<PosMenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(PosMenuItem::class);
    }

    /**
     * @return HasMany<PosOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }

    /**
     * The POS staff assigned to this outlet.
     *
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pos_outlet_user')
            ->withPivot('team_id')
            ->withTimestamps();
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
