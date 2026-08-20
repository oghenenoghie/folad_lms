<?php

namespace App\Policies;

use App\Models\TimetableEntry;
use App\Models\User;

class TimetableEntryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** The timetable isn't sensitive -- any authenticated member of the school can read it. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TimetableEntry $timetableEntry): bool
    {
        return true;
    }

    /** Building the timetable is an academic-authority action, not a per-teacher self-service one. */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher']);
    }

    public function update(User $user, TimetableEntry $timetableEntry): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher']);
    }

    public function delete(User $user, TimetableEntry $timetableEntry): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher']);
    }
}
