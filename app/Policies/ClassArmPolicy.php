<?php

namespace App\Policies;

use App\Models\ClassArm;
use App\Models\User;

class ClassArmPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->school_id !== null;
    }

    public function view(User $user, ClassArm $classArm): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $classArm->school_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, ClassArm $classArm): bool
    {
        return $this->create($user) && $this->view($user, $classArm);
    }

    public function delete(User $user, ClassArm $classArm): bool
    {
        return $this->update($user, $classArm);
    }
}
