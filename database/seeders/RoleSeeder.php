<?php

namespace Database\Seeders;

use App\Http\Middleware\SetPermissionsTeam;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles are teamed by school_id (config/permission.php), so — aside from
 * super_admin, which lives under the SetPermissionsTeam::GLOBAL_TEAM_ID
 * sentinel — every role row belongs to one school. seedForSchool() is
 * called from School's `created` model event so a newly onboarded school
 * gets its own role set automatically; this seeder's run() only needs to
 * create the global super_admin role.
 */
class RoleSeeder extends Seeder
{
    public const SCHOOL_ROLES = [
        'school_admin', 'teacher', 'student', 'guardian', 'accountant', 'bursar', 'head_teacher',
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId(SetPermissionsTeam::GLOBAL_TEAM_ID);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $registrar->setPermissionsTeamId($previousTeamId);
    }

    public static function seedForSchool(int $schoolId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($schoolId);

        foreach (self::SCHOOL_ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $registrar->setPermissionsTeamId($previousTeamId);
    }
}
