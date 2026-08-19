<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;

class TermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->school_id !== null;
    }

    public function view(User $user, Term $term): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $term->school_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('school_admin');
    }

    public function update(User $user, Term $term): bool
    {
        return $this->create($user) && $this->view($user, $term);
    }

    public function delete(User $user, Term $term): bool
    {
        return $this->update($user, $term);
    }
}
