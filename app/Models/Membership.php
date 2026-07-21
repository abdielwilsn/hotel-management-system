<?php

namespace App\Models;

use App\Enums\Ability;
use App\Enums\DataScope;
use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

#[Fillable(['team_id', 'user_id', 'role', 'data_scope'])]
class Membership extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'team_members';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Memberships are memoised per request for policy checks; any write has
        // to invalidate that or the rest of the request runs on stale abilities.
        static::saved(fn () => User::flushTeamMembershipCache());
        static::deleted(fn () => User::flushTeamMembershipCache());
    }

    /**
     * Get the team that the membership belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that belongs to this membership.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The abilities this membership grants on its own.
     *
     * Permissions are granted by departments; this is only the safety net for
     * somebody who has not been put in one yet, and for owners, who are never
     * allowed to lock themselves out.
     *
     * @return Collection<int, Ability>
     */
    public function abilities(): Collection
    {
        if ($this->role === TeamRole::Owner) {
            return collect(Ability::cases());
        }

        return collect($this->role->defaultAbilityValues())
            ->map(fn (string $ability) => Ability::tryFrom($ability))
            ->filter()
            ->values();
    }

    /**
     * Determine if this membership grants the given ability.
     */
    public function hasAbility(Ability $ability): bool
    {
        return $this->abilities()->contains($ability);
    }

    /**
     * Determine if this membership can see records from every department.
     */
    public function seesAllDepartments(): bool
    {
        return $this->role === TeamRole::Owner
            || $this->data_scope === DataScope::All;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            'data_scope' => DataScope::class,
        ];
    }
}
