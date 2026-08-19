<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasAnyRole(['school_admin', 'teacher', 'head_teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, Staff $staff): bool
    {
        return ($user->isSuperAdmin() || $user->school_id === $staff->school_id)
            && ($this->viewAny($user) || $user->staff?->id === $staff->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $this->create($user) && ($user->isSuperAdmin() || $user->school_id === $staff->school_id);
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $this->update($user, $staff);
    }
}
