<?php

namespace Tests\Feature;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Guardian as GuardianModel;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentApiTest extends TestCase
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

    protected function makeStudent(School $school, array $overrides = []): Student
    {
        return Student::create(array_merge([
            'school_id'        => $school->id,
            'admission_number' => 'ADM-'.$school->id.'-'.random_int(1000, 9999),
            'first_name'       => 'Ada',
            'last_name'        => 'Okafor',
            'gender'           => 'female',
            'date_of_birth'    => '2012-01-01',
        ], $overrides));
    }

    public function test_school_admin_only_sees_students_from_their_own_school(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $this->makeStudent($schoolA, ['first_name' => 'Alice']);
        $this->makeStudent($schoolB, ['first_name' => 'Bob']);

        $admin = $this->makeSchoolAdmin($schoolA);

        $response = $this->actingAs($admin)->getJson('/api/students');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('first_name');
        $this->assertTrue($names->contains('Alice'));
        $this->assertFalse($names->contains('Bob'));
    }

    public function test_school_admin_can_create_a_student(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/students', [
            'admission_number' => 'ADM-100',
            'first_name'       => 'Chinedu',
            'last_name'        => 'Eze',
            'gender'           => 'male',
            'date_of_birth'    => '2011-05-20',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.admission_number', 'ADM-100');
        $this->assertDatabaseHas('students', [
            'admission_number' => 'ADM-100',
            'school_id'        => $school->id,
        ]);
    }

    public function test_admission_number_must_be_unique_within_a_school_but_not_across_schools(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $this->makeStudent($schoolA, ['admission_number' => 'ADM-1']);
        $adminA = $this->makeSchoolAdmin($schoolA);
        $adminB = $this->makeSchoolAdmin($schoolB);

        $duplicate = $this->actingAs($adminA)->postJson('/api/students', [
            'admission_number' => 'ADM-1',
            'first_name'       => 'Second',
            'last_name'        => 'Student',
            'gender'           => 'male',
            'date_of_birth'    => '2011-05-20',
        ]);
        $duplicate->assertStatus(422);

        $sameNumberOtherSchool = $this->actingAs($adminB)->postJson('/api/students', [
            'admission_number' => 'ADM-1',
            'first_name'       => 'Third',
            'last_name'        => 'Student',
            'gender'           => 'male',
            'date_of_birth'    => '2011-05-20',
        ]);
        $sameNumberOtherSchool->assertCreated();
    }

    public function test_a_student_from_another_school_is_not_visible_or_editable(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $foreignStudent = $this->makeStudent($schoolB);
        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/students/{$foreignStudent->id}")->assertNotFound();
        $this->actingAs($adminA)->putJson("/api/students/{$foreignStudent->id}", ['first_name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_teacher_can_view_but_not_create_students(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $student = $this->makeStudent($school);

        $teacher = User::create([
            'school_id' => $school->id,
            'name'      => 'Teacher',
            'email'     => 'teacher@example.com',
            'password'  => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $teacher->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web', 'school_id' => $school->id]));

        $this->actingAs($teacher)->getJson("/api/students/{$student->id}")->assertOk();

        $this->actingAs($teacher)->postJson('/api/students', [
            'admission_number' => 'ADM-200',
            'first_name'       => 'New',
            'last_name'        => 'Student',
            'gender'           => 'male',
            'date_of_birth'    => '2011-05-20',
        ])->assertForbidden();
    }

    public function test_guardian_can_only_view_their_own_linked_student(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $ownStudent = $this->makeStudent($school, ['first_name' => 'Own']);
        $otherStudent = $this->makeStudent($school, ['first_name' => 'Other']);

        $guardianUser = User::create([
            'school_id' => $school->id,
            'name'      => 'Parent',
            'email'     => 'parent@example.com',
            'password'  => 'secret',
        ]);
        $guardian = GuardianModel::create([
            'school_id' => $school->id,
            'user_id'   => $guardianUser->id,
            'first_name' => 'Parent',
            'last_name'  => 'One',
            'phone'      => '08000000000',
        ]);
        $guardian->students()->attach($ownStudent->id, ['relationship' => 'Mother', 'is_primary_contact' => true]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $school->id]));

        $this->actingAs($guardianUser)->getJson("/api/students/{$ownStudent->id}")->assertOk();
        $this->actingAs($guardianUser)->getJson("/api/students/{$otherStudent->id}")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }

    public function test_unauthenticated_plain_request_returns_401_not_a_redirect_crash(): void
    {
        // No Accept: application/json header — e.g. a bare browser hit. Without
        // redirectGuestsTo(null) in bootstrap/app.php, this throws a
        // RouteNotFoundException trying to build a nonexistent 'login' route.
        $this->get('/api/students')->assertUnauthorized();
    }

    public function test_school_admin_can_soft_delete_and_restore_a_student(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $student = $this->makeStudent($school);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->deleteJson("/api/students/{$student->id}")->assertNoContent();
        $this->assertSoftDeleted('students', ['id' => $student->id]);

        $this->actingAs($admin)->postJson("/api/students/{$student->id}/restore")->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'deleted_at' => null]);
    }

    public function test_response_includes_derived_current_class(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $school->academicSessions()->create([
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ]);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $arm = ClassArm::create(['school_id' => $school->id, 'class_level_id' => $level->id, 'name' => 'A']);
        $student = $this->makeStudent($school);
        $student->enrollments()->create([
            'school_id' => $school->id,
            'class_arm_id' => $arm->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $admin = $this->makeSchoolAdmin($school);
        $response = $this->actingAs($admin)->getJson("/api/students/{$student->id}");

        $response->assertOk();
        $response->assertJsonPath('data.current_class.class_arm', 'JSS 1A');
    }
}
