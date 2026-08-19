<?php

namespace App\Policies;

use App\Models\AssessmentComponent;
use App\Models\User;

class AssessmentComponentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** Not sensitive — any authenticated member of the school can read the term's assessment structure. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AssessmentComponent $assessmentComponent): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('school_admin');
    }

    public function update(User $user, AssessmentComponent $assessmentComponent): bool
    {
        return $user->hasRole('school_admin');
    }

    public function delete(User $user, AssessmentComponent $assessmentComponent): bool
    {
        return $user->hasRole('school_admin');
    }
}
