<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use App\Enums\TeamRole;
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
     * General team staff (Member and above) can access every outlet on their team;
     * POS-only staff are limited to the outlets they are assigned to.
     */
    public function canAccessPosOutlet(PosOutlet $outlet): bool
    {
        if (! $this->belongsToTeam($outlet->team)) {
            return false;
        }

        $role = $this->teamRole($outlet->team);

        if ($role !== null && $role->isAtLeast(TeamRole::Member)) {
            return true;
        }

        return $this->posOutlets()
            ->where('pos_outlets.id', $outlet->id)
            ->exists();
    }
}
