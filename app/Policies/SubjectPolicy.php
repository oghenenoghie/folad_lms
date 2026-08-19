<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->school_id !== null;
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $subject->school_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, Subject $subject): bool
    {
        return $this->create($user) && $this->view($user, $subject);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->update($user, $subject);
    }
}
