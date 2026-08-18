<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\ClassArm;
use App\Models\User;

class AttendancePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'teacher', 'accountant', 'bursar'])) {
            return true;
        }

        $studentId = $attendance->enrollment?->student_id;

        if ($user->hasRole('guardian')) {
            return $studentId && ($user->guardian?->students()->whereKey($studentId)->exists() ?? false);
        }

        if ($user->hasRole('student')) {
            return $user->student?->id === $studentId;
        }

        return false;
    }

    /**
     * Unlike results (no per-subject teacher-assignment model exists), a
     * class_arm's form_teacher_id is real data we already have -- so this
     * gets to be precise: school_admin, or the teacher who actually owns
     * this class, not any teacher school-wide.
     */
    public function markForClassArm(User $user, ClassArm $classArm): bool
    {
        return $user->hasRole('school_admin')
            || ($user->hasRole('teacher') && $user->staff?->id === $classArm->form_teacher_id);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        $formTeacherId = $attendance->enrollment?->classArm?->form_teacher_id;

        return $user->hasRole('school_admin')
            || ($user->hasRole('teacher') && $user->staff?->id === $formTeacherId);
    }

    /** Removing a record outright (vs. correcting it) stays admin-only. */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->hasRole('school_admin');
    }
}
