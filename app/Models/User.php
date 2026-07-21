<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use App\Enums\Ability;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The POS outlets this user is explicitly assigned to (used to scope POS staff).
     *
     * @return BelongsToMany<PosOutlet, $this>
     */
    public function posOutlets(): BelongsToMany
    {
        return $this->belongsToMany(PosOutlet::class, 'pos_outlet_user')
            ->withPivot('team_id')
            ->withTimestamps();
    }

    /**
     * Determine whether the user may operate the given POS outlet.
     *
     * Access narrows in three steps: the user must be allowed to work a till at
     * all, the outlet must fall inside their department scope, and POS-only
     * staff are further limited to the outlets they are explicitly assigned to.
     */
    public function canAccessPosOutlet(PosOutlet $outlet): bool
    {
        $team = $outlet->team;

        if (! $this->hasAbility(Ability::OperatePos, $team)) {
            return false;
        }

        if (! $this->canAccessDepartment($team, $outlet->department_id)) {
            return false;
        }

        // Department-scoped staff have already earned this outlet by working in
        // the department that owns it; a second per-outlet roster would only be
        // another thing to keep in step.
        if ($this->visibleDepartmentIds($team) !== null) {
            return true;
        }

        if ($this->hasAbility(Ability::AccessHotel, $team)) {
            return true;
        }

        return $this->posOutlets()
            ->where('pos_outlets.id', $outlet->id)
            ->exists();
    }
}
