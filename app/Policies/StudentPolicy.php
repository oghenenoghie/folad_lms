<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    protected function isSchoolStaff(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'teacher', 'head_teacher', 'accountant', 'bursar']);
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->isSchoolStaff($user);
    }

    public function view(User $user, Student $student): bool
    {
        if (! ($user->isSuperAdmin() || $user->school_id === $student->school_id)) {
            return false;
        }

        return $this->isSchoolStaff($user)
            || $user->isSuperAdmin()
            || $user->student?->id === $student->id
            || ($user->guardian && $student->guardians()->where('guardians.id', $user->guardian->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, Student $student): bool
    {
        return $this->create($user) && ($user->isSuperAdmin() || $user->school_id === $student->school_id);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }
}
