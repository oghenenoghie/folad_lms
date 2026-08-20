<?php

namespace App\Policies;

use App\Models\Period;
use App\Models\User;

class PeriodPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** The daily schedule isn't sensitive -- any authenticated member of the school can read it. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Period $period): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, Period $period): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, Period $period): bool
    {
        return $user->hasRole('school_admin');
    }
}
