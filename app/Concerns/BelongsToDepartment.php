<?php

namespace App\Concerns;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Row level filtering for models that hang off a department.
 *
 * Policies decide whether a user may act on a single record; this decides which
 * records they are shown in the first place. Both read the same department
 * assignments, so a listing and a detail page can never disagree.
 */
trait BelongsToDepartment
{
    /**
     * Limit the query to the departments the user is allowed to see.
     *
     * Users with an unrestricted data scope are not filtered at all.
     */
    public function scopeVisibleTo(Builder $query, User $user, Team $team): void
    {
        $departmentIds = $user->visibleDepartmentIds($team);

        if ($departmentIds === null) {
            return;
        }

        $query->whereIn($this->qualifyColumn('department_id'), $departmentIds);
    }
}
