<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/**
 * School is the tenant root -- it carries no school_id and has no SchoolScope,
 * so unlike every other policy in this app, access here has to be checked
 * explicitly rather than falling out of a pre-filtered query.
 */
class SchoolPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** Listing every tenant is a platform-owner action. */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /** A tenant user can see their own school's profile, never another's. */
    public function view(User $user, School $school): bool
    {
        return $user->school_id === $school->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, School $school): bool
    {
        return $user->hasRole('school_admin') && $user->school_id === $school->id;
    }

    public function delete(User $user, School $school): bool
    {
        return false;
    }

    public function restore(User $user, School $school): bool
    {
        return false;
    }
}
