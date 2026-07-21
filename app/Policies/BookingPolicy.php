<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ViewBookings, $team);
    }

    public function view(User $user, Booking $booking, Team $team): bool
    {
        return $booking->team_id === $team->id && $user->hasAbility(Ability::ViewBookings, $team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasAbility(Ability::ManageBookings, $team);
    }

    public function update(User $user, Booking $booking, Team $team): bool
    {
        return $booking->team_id === $team->id && $user->hasAbility(Ability::ManageBookings, $team);
    }

    public function delete(User $user, Booking $booking, Team $team): bool
    {
        return $booking->team_id === $team->id && $user->hasAbility(Ability::DeleteBookings, $team);
    }

    public function restore(User $user, Booking $booking): bool
    {
        return false;
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        return false;
    }
}
