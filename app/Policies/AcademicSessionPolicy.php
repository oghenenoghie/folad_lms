<?php

namespace App\Policies;

use App\Models\AcademicSession;
use App\Models\User;

class AcademicSessionPolicy
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

    public function view(User $user, AcademicSession $academicSession): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasRole('school_admin');
    }
}
