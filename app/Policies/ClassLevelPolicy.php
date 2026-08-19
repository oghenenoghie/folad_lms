<?php

namespace App\Policies;

use App\Models\ClassLevel;
use App\Models\User;

class ClassLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->school_id !== null;
    }

    public function view(User $user, ClassLevel $classLevel): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $classLevel->school_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, ClassLevel $classLevel): bool
    {
        return $this->create($user) && $this->view($user, $classLevel);
    }

    public function delete(User $user, ClassLevel $classLevel): bool
    {
        return $this->update($user, $classLevel);
    }
}
