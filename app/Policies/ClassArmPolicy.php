<?php

namespace App\Policies;

use App\Models\ClassArm;
use App\Models\User;

class ClassArmPolicy
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

    /** Class structure isn't sensitive — any authenticated member of the school can read it. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ClassArm $classArm): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, ClassArm $classArm): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, ClassArm $classArm): bool
    {
        return $user->hasRole('school_admin');
    }
}
