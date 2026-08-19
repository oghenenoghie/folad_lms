<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates the login (User + spatie role) for a staff/student/guardian
 * profile that was created without one. Kept out of the profile
 * controllers so "create the record" and "grant it a login" stay separate,
 * explicit actions — not every profile needs a login (e.g. support staff).
 */
class AccountProvisioner
{
    public static function createFor(string $name, string $email, ?int $schoolId, string $role, ?string $password = null): User
    {
        $user = User::create([
            'school_id' => $schoolId,
            'name'      => $name,
            'email'     => $email,
            'password'  => $password ?? Str::password(16),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
