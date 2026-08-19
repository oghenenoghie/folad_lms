<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy
{
    /**
     * super_admin crosses tenants and can do anything; every other check below
     * already runs against a query/model pre-filtered to the user's own school
     * by the SchoolScope global scope, so no explicit school_id comparison is
     * needed here.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, Guardian $guardian): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar'])) {
            return true;
        }

        if ($user->hasRole('guardian')) {
            return $user->guardian?->id === $guardian->id;
        }

        if ($user->hasRole('student')) {
            return $user->student?->guardians()->whereKey($guardian->id)->exists() ?? false;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->hasRole('school_admin')
            || ($user->hasRole('guardian') && $user->guardian?->id === $guardian->id);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->hasRole('school_admin');
    }

    public function restore(User $user, Guardian $guardian): bool
    {
        return $user->hasRole('school_admin');
    }

    /** Linking/unlinking students is an admin (enrolment-office) action, not self-service. */
    public function manageStudents(User $user, Guardian $guardian): bool
    {
        return $user->hasRole('school_admin');
    }
}
