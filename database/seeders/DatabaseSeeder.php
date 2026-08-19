<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\School;
use App\Models\Subject;
use App\Http\Middleware\SetPermissionsTeam;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Local/dev bootstrap data: a super_admin, one demo school with a
 * school_admin, and enough of the academic calendar + class structure to
 * exercise the frontend against real data. Does NOT run in production —
 * cPanel deploy only runs `migrate --force`, never `db:seed`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $this->call(RoleSeeder::class);

        $registrar->setPermissionsTeamId(SetPermissionsTeam::GLOBAL_TEAM_ID);

        $superAdmin = User::factory()->create([
            'name'      => 'Folad Super Admin',
            'email'     => 'superadmin@folad.test',
            'password'  => 'password',
            'school_id' => null,
        ]);
        $superAdmin->assignRole('super_admin');

        $school = School::create([
            'name'     => 'Folad Demo School',
            'code'     => 'demo',
            'email'    => 'info@folad-demo.test',
            'phone'    => '+2348000000000',
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        $registrar->setPermissionsTeamId($school->id);

        $schoolAdmin = User::factory()->create([
            'name'      => 'Demo School Admin',
            'email'     => 'admin@folad-demo.test',
            'password'  => 'password',
            'school_id' => $school->id,
        ]);
        $schoolAdmin->assignRole('school_admin');

        $session = AcademicSession::create([
            'school_id'  => $school->id,
            'name'       => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date'   => '2026-07-31',
            'is_current' => true,
        ]);

        foreach ([
            ['name' => 'First Term', 'sequence' => 1, 'start_date' => '2025-09-01', 'end_date' => '2025-12-12', 'is_current' => false],
            ['name' => 'Second Term', 'sequence' => 2, 'start_date' => '2026-01-05', 'end_date' => '2026-04-02', 'is_current' => true],
            ['name' => 'Third Term', 'sequence' => 3, 'start_date' => '2026-04-20', 'end_date' => '2026-07-31', 'is_current' => false],
        ] as $term) {
            Term::create([...$term, 'school_id' => $school->id, 'academic_session_id' => $session->id]);
        }

        $jss1 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $jss2 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 2', 'rank' => 2]);

        ClassArm::create(['school_id' => $school->id, 'class_level_id' => $jss1->id, 'name' => 'A', 'capacity' => 40]);
        ClassArm::create(['school_id' => $school->id, 'class_level_id' => $jss2->id, 'name' => 'A', 'capacity' => 40]);

        foreach ([
            ['name' => 'Mathematics', 'code' => 'MTH', 'is_core' => true],
            ['name' => 'English Language', 'code' => 'ENG', 'is_core' => true],
            ['name' => 'Basic Science', 'code' => 'BSC', 'is_core' => false],
        ] as $subject) {
            Subject::create([...$subject, 'school_id' => $school->id]);
        }

        $registrar->setPermissionsTeamId(SetPermissionsTeam::GLOBAL_TEAM_ID);
    }
}
