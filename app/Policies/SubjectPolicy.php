<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** The subject catalog isn't sensitive — any authenticated member of the school can read it. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subject $subject): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->hasRole('school_admin');
    }
}
