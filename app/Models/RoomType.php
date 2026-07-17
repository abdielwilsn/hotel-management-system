<?php

namespace App\Models;

use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'slug',
        'name',
    ];

    /**
     * The types every new team starts with.
     *
     * @var list<array{slug: string, name: string}>
     */
    public const DEFAULTS = [
        ['slug' => 'single', 'name' => 'Single'],
        ['slug' => 'double', 'name' => 'Double'],
        ['slug' => 'suite', 'name' => 'Suite'],
        ['slug' => 'deluxe', 'name' => 'Deluxe'],
        ['slug' => 'penthouse', 'name' => 'Penthouse'],
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Rooms are linked by slug rather than a foreign key, which keeps the type
     * renameable without touching every room.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type', 'slug')
            ->where('team_id', $this->team_id);
    }

    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    /**
     * Build a slug that is unique within the team.
     */
    public static function uniqueSlugFor(Team $team, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'type';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('team_id', $team->id)
                ->where('slug', $slug)
                ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
