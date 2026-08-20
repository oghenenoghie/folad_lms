<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar'])) {
            return true;
        }

        $studentId = $payment->invoice?->enrollment?->student_id;

        if ($user->hasRole('guardian')) {
            return $studentId && ($user->guardian?->students()->whereKey($studentId)->exists() ?? false);
        }

        if ($user->hasRole('student')) {
            return $user->student?->id === $studentId;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'accountant', 'bursar']);
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['school_admin', 'accountant', 'bursar']);
    }

    /** Payments are append-only -- correct a mistake with reverse(), never an edit. No route calls this. */
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    /** Payments are append-only -- correct a mistake with reverse(), never a delete. No route calls this. */
    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}
