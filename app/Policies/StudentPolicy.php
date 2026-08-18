<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
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

    public function view(User $user, Student $student): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar'])) {
            return true;
        }

        if ($user->hasRole('guardian')) {
            return $user->guardian?->students()->whereKey($student->id)->exists() ?? false;
        }

        if ($user->hasRole('student')) {
            return $user->student?->id === $student->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasRole('school_admin');
    }

    public function restore(User $user, Student $student): bool
    {
        return $user->hasRole('school_admin');
    }
}
