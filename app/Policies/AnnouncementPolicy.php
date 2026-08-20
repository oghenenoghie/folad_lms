<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Delegates to the same scope the index listing filters by, so the two can never drift apart. */
    public function view(User $user, Announcement $announcement): bool
    {
        return Announcement::query()->visibleTo($user)->whereKey($announcement->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher']);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher']);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher']);
    }
}
