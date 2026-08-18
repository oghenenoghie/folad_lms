<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;

class TermPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** The calendar isn't sensitive — any authenticated member of the school can read it. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Term $term): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, Term $term): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, Term $term): bool
    {
        return $user->hasRole('school_admin');
    }
}
