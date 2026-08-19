<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
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

    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar'])) {
            return true;
        }

        if ($user->hasRole('guardian')) {
            return $user->guardian?->students()->whereKey($enrollment->student_id)->exists() ?? false;
        }

        if ($user->hasRole('student')) {
            return $user->student?->id === $enrollment->student_id;
        }

        return false;
    }

    /** Placement is an enrolment-office action, not something staff do ad hoc. */
    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->hasRole('school_admin');
    }
}
