<?php

namespace App\Policies;

use App\Models\FeeStructure;
use App\Models\User;

class FeeStructurePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'accountant', 'bursar']);
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasAnyRole(['school_admin', 'accountant', 'bursar']);
    }

    public function publish(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasAnyRole(['school_admin', 'accountant', 'bursar']);
    }

    public function delete(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasAnyRole(['school_admin', 'accountant', 'bursar']);
    }
}
