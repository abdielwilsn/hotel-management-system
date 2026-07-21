<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Team;
use App\Models\User;

class BookingDiscountPolicy
{
    /**
     * Front desk may request a discount.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::RequestDiscounts, $team);
    }

    /**
     * Only those trusted with review may approve or reject discounts.
     */
    public function review(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ReviewDiscounts, $team);
    }
}
