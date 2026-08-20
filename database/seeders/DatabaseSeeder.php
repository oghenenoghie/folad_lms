<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $school = School::firstOrCreate(
            ['code' => 'demo'],
            [
                'name'      => 'Folad Demo School',
                'email'     => 'info@demo.folad.test',
                'currency'  => 'NGN',
                'is_active' => true,
            ],
        );

        // super_admin has a NULL school_id and bypasses the tenant scope
        // entirely (see User::isSuperAdmin()) -- it isn't a spatie role.
        User::firstOrCreate(
            ['email' => 'superadmin@folad.test'],
            [
                'school_id' => null,
                'name'      => 'Super Admin',
                'password'  => 'password',
            ],
        );

        $schoolAdmin = User::firstOrCreate(
            ['email' => 'admin@demo.folad.test'],
            [
                'school_id' => $school->id,
                'name'      => 'Demo School Admin',
                'password'  => 'password',
            ],
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        if (! $schoolAdmin->hasRole('school_admin')) {
            $schoolAdmin->assignRole(
                Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web', 'school_id' => $school->id]),
            );
        }

        $this->command?->info('Seeded demo school (code: demo) with:');
        $this->command?->info('  superadmin@folad.test / password  (super_admin, no school)');
        $this->command?->info('  admin@demo.folad.test / password  (school_admin, Folad Demo School)');
    }
}
