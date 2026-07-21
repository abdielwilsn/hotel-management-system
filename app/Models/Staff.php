<?php

namespace App\Models;

use App\Concerns\BelongsToDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    use BelongsToDepartment, HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'team_id',
        'department_id',
        'full_name',
        'email',
        'phone',
        'address',
        'gender',
        'role',
        'employment_date',
        'salary',
        'emergency_contact_name',
        'emergency_contact_phone',
        'profile_image_path',
        'status',
    ];

    protected $casts = [
        'employment_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'email', 'email');
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
