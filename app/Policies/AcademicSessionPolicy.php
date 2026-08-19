<?php

namespace App\Policies;

use App\Models\AcademicSession;
use App\Models\User;

class AcademicSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->school_id !== null;
    }

    public function view(User $user, AcademicSession $academicSession): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $academicSession->school_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, AcademicSession $academicSession): bool
    {
        return $this->create($user) && $this->view($user, $academicSession);
    }

    public function delete(User $user, AcademicSession $academicSession): bool
    {
        return $this->update($user, $academicSession);
    }
}
