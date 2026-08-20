<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Resolves the "current school" for tenant scoping.
 *
 * Resolution order:
 *   1. If disabled  -> null (no scoping; system/super_admin context)
 *   2. Explicit override (set in jobs, console commands, super_admin acting-as)
 *   3. The authenticated user's school_id
 *
 * A super_admin user has a NULL school_id, so schoolId() returns null for them
 * and the global scope is skipped — they see across every tenant by design.
 */
class Tenancy
{
    protected static ?int $overrideSchoolId = null;
    protected static bool $disabled = false;

    public static function useSchool(?int $schoolId): void
    {
        static::$overrideSchoolId = $schoolId;
    }

    public static function disable(): void
    {
        static::$disabled = true;
    }

    public static function enable(): void
    {
        static::$disabled = false;
        static::$overrideSchoolId = null;
    }

    public static function schoolId(): ?int
    {
        if (static::$disabled) {
            return null;
        }

        if (static::$overrideSchoolId !== null) {
            return static::$overrideSchoolId;
        }

        return Auth::user()?->school_id;
    }
}
