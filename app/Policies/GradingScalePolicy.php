<?php

namespace App\Policies;

use App\Models\GradingScale;
use App\Models\User;

class GradingScalePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** The grading scheme isn't sensitive — any authenticated member of the school can read it. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GradingScale $gradingScale): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, GradingScale $gradingScale): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, GradingScale $gradingScale): bool
    {
        return $user->hasRole('school_admin');
    }
}
