<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    protected function isSchoolStaff(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'teacher', 'head_teacher']);
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->isSchoolStaff($user);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return ($user->isSuperAdmin() || $user->school_id === $enrollment->school_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $this->create($user) && ($user->isSuperAdmin() || $user->school_id === $enrollment->school_id);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->update($user, $enrollment);
    }
}
