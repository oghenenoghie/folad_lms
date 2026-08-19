<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy
{
    protected function isSchoolStaff(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'teacher', 'head_teacher', 'accountant', 'bursar']);
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->isSchoolStaff($user);
    }

    public function view(User $user, Guardian $guardian): bool
    {
        if (! ($user->isSuperAdmin() || $user->school_id === $guardian->school_id)) {
            return false;
        }

        return $user->isSuperAdmin() || $this->isSchoolStaff($user) || $user->guardian?->id === $guardian->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->create($user) && ($user->isSuperAdmin() || $user->school_id === $guardian->school_id);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $this->update($user, $guardian);
    }
}
