<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher', 'accountant', 'bursar'])) {
            return true;
        }

        $studentId = $invoice->enrollment?->student_id;

        if ($user->hasRole('guardian')) {
            return $studentId && ($user->guardian?->students()->whereKey($studentId)->exists() ?? false);
        }

        if ($user->hasRole('student')) {
            return $user->student?->id === $studentId;
        }

        return false;
    }

    /** Only correcting the due date is exposed; the bill amount is locked once generated (see UpdateInvoiceRequest). */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasAnyRole(['school_admin', 'accountant', 'bursar']);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('school_admin');
    }
}
