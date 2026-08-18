<?php

namespace App\Policies;

use App\Models\Result;
use App\Models\User;

class ResultPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, Result $result): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar'])) {
            return true;
        }

        $studentId = $result->enrollment?->student_id;

        if ($user->hasRole('guardian')) {
            return $studentId && ($user->guardian?->students()->whereKey($studentId)->exists() ?? false);
        }

        if ($user->hasRole('student')) {
            return $user->student?->id === $studentId;
        }

        return false;
    }

    /** Recording scores is a teaching-staff action; no per-subject assignment model exists yet to scope it tighter. */
    public function create(User $user): bool
    {
        return $user->hasRole('school_admin') || $user->hasRole('teacher');
    }

    public function update(User $user, Result $result): bool
    {
        return $user->hasRole('school_admin') || $user->hasRole('teacher');
    }

    /** Removing a recorded score outright (vs. correcting it) stays admin-only. */
    public function delete(User $user, Result $result): bool
    {
        return $user->hasRole('school_admin');
    }
}
