<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
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

    /** Staff records are HR data — listing everyone is admin-only, not a teacher perk. */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, Staff $staff): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar'])) {
            return true;
        }

        // A staff member (teacher, etc.) can always see their own record.
        return $user->staff?->id === $staff->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->hasRole('school_admin') || $user->staff?->id === $staff->id;
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $user->hasRole('school_admin');
    }

    public function restore(User $user, Staff $staff): bool
    {
        return $user->hasRole('school_admin');
    }
}
