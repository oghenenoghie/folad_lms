<?php

namespace Tests\Feature;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClassStructureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSchoolAdmin(School $school): User
    {
        $user = User::create([
            'school_id' => $school->id,
            'name'      => 'Admin '.$school->id,
            'email'     => "admin{$school->id}@example.com",
            'password'  => 'secret',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $role = Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web', 'school_id' => $school->id]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeTeacherUser(School $school): User
    {
        $user = User::create([
            'school_id' => $school->id,
            'name'      => 'Teacher',
            'email'     => 'teacher'.random_int(1000, 9999).'@example.com',
            'password'  => 'secret',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $user->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web', 'school_id' => $school->id]));

        return $user;
    }

    public function test_school_admin_can_create_a_class_level(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/class-levels', [
            'name' => 'JSS 1',
            'rank' => 1,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('class_levels', ['name' => 'JSS 1', 'school_id' => $school->id]);
    }

    public function test_class_level_name_must_be_unique_within_a_school_but_not_across_schools(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        ClassLevel::create(['school_id' => $schoolA->id, 'name' => 'JSS 1', 'rank' => 1]);
        $adminA = $this->makeSchoolAdmin($schoolA);
        $adminB = $this->makeSchoolAdmin($schoolB);

        $this->actingAs($adminA)->postJson('/api/class-levels', ['name' => 'JSS 1', 'rank' => 2])
            ->assertStatus(422);

        $this->actingAs($adminB)->postJson('/api/class-levels', ['name' => 'JSS 1', 'rank' => 1])
            ->assertCreated();
    }

    public function test_teacher_can_view_but_not_create_class_levels(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $teacher = $this->makeTeacherUser($school);

        $this->actingAs($teacher)->getJson('/api/class-levels')->assertOk();
        $this->actingAs($teacher)->getJson("/api/class-levels/{$level->id}")->assertOk();
        $this->actingAs($teacher)->postJson('/api/class-levels', ['name' => 'JSS 2', 'rank' => 2])
            ->assertForbidden();
    }

    public function test_a_class_level_from_another_school_is_not_visible(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $foreignLevel = ClassLevel::create(['school_id' => $schoolB->id, 'name' => 'JSS 1', 'rank' => 1]);
        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/class-levels/{$foreignLevel->id}")->assertNotFound();
    }

    public function test_cannot_delete_a_class_level_that_still_has_arms(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        ClassArm::create(['school_id' => $school->id, 'class_level_id' => $level->id, 'name' => 'A']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->deleteJson("/api/class-levels/{$level->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('class_levels', ['id' => $level->id]);
    }

    public function test_school_admin_can_delete_an_empty_class_level(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->deleteJson("/api/class-levels/{$level->id}")->assertNoContent();
        $this->assertDatabaseMissing('class_levels', ['id' => $level->id]);
    }

    public function test_school_admin_can_create_a_class_arm_with_a_form_teacher(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $teacher = Staff::create([
            'school_id' => $school->id, 'staff_number' => 'STF-1', 'first_name' => 'Tunde', 'last_name' => 'Bello',
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/class-arms', [
            'class_level_id'  => $level->id,
            'name'            => 'A',
            'form_teacher_id' => $teacher->id,
            'capacity'        => 40,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.full_name', 'JSS 1A');
        $response->assertJsonPath('data.form_teacher.full_name', 'Tunde Bello');
    }

    public function test_class_arm_name_must_be_unique_within_a_level_but_not_across_levels(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $jss1 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $jss2 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 2', 'rank' => 2]);
        ClassArm::create(['school_id' => $school->id, 'class_level_id' => $jss1->id, 'name' => 'A']);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/class-arms', ['class_level_id' => $jss1->id, 'name' => 'A'])
            ->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/class-arms', ['class_level_id' => $jss2->id, 'name' => 'A'])
            ->assertCreated();
    }

    public function test_form_teacher_must_belong_to_the_same_school(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        $level = ClassLevel::create(['school_id' => $schoolA->id, 'name' => 'JSS 1', 'rank' => 1]);
        $foreignTeacher = Staff::create([
            'school_id' => $schoolB->id, 'staff_number' => 'STF-1', 'first_name' => 'Foreign', 'last_name' => 'Teacher',
        ]);
        $admin = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($admin)->postJson('/api/class-arms', [
            'class_level_id'  => $level->id,
            'name'            => 'A',
            'form_teacher_id' => $foreignTeacher->id,
        ])->assertStatus(422);
    }

    public function test_index_can_filter_by_class_level_and_reports_active_enrolled_count(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $school->academicSessions()->create([
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ]);
        $jss1 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $jss2 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 2', 'rank' => 2]);
        $armA = ClassArm::create(['school_id' => $school->id, 'class_level_id' => $jss1->id, 'name' => 'A']);
        ClassArm::create(['school_id' => $school->id, 'class_level_id' => $jss2->id, 'name' => 'A']);

        $student = Student::create([
            'school_id' => $school->id, 'admission_number' => 'ADM-1', 'first_name' => 'Ada', 'last_name' => 'Okafor',
            'gender' => 'female', 'date_of_birth' => '2012-01-01',
        ]);
        Enrollment::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'class_arm_id' => $armA->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);

        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->getJson("/api/class-arms?class_level_id={$jss1->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.enrolled_count', 1);
    }

    public function test_cannot_delete_a_class_arm_with_enrollments(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $school->academicSessions()->create([
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ]);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $arm = ClassArm::create(['school_id' => $school->id, 'class_level_id' => $level->id, 'name' => 'A']);
        $student = Student::create([
            'school_id' => $school->id, 'admission_number' => 'ADM-1', 'first_name' => 'Ada', 'last_name' => 'Okafor',
            'gender' => 'female', 'date_of_birth' => '2012-01-01',
        ]);
        Enrollment::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->deleteJson("/api/class-arms/{$arm->id}")->assertStatus(409);
        $this->assertDatabaseHas('class_arms', ['id' => $arm->id]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/class-levels')->assertUnauthorized();
        $this->getJson('/api/class-arms')->assertUnauthorized();
    }
}
