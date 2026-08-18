<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\Guardian as GuardianModel;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EnrollmentApiTest extends TestCase
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

    protected function makeSession(School $school, array $overrides = []): AcademicSession
    {
        return $school->academicSessions()->create(array_merge([
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ], $overrides));
    }

    protected function makeArm(School $school, string $levelName = 'JSS 1'): ClassArm
    {
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => $levelName, 'rank' => 1]);

        return ClassArm::create(['school_id' => $school->id, 'class_level_id' => $level->id, 'name' => 'A']);
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

    public function test_school_admin_can_enroll_a_student(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $this->makeSession($school);
        $arm = $this->makeArm($school);
        $student = $this->makeStudent($school);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/enrollments', [
            'student_id'          => $student->id,
            'class_arm_id'        => $arm->id,
            'academic_session_id' => $session->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.class_arm.full_name', 'JSS 1A');
        $this->assertDatabaseHas('enrollments', [
            'student_id'          => $student->id,
            'class_arm_id'        => $arm->id,
            'academic_session_id' => $session->id,
            'status'              => 'active',
        ]);
    }

    public function test_a_student_cannot_have_two_enrollments_in_the_same_session(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $this->makeSession($school);
        $armA = $this->makeArm($school, 'JSS 1');
        $armB = $this->makeArm($school, 'JSS 2');
        $student = $this->makeStudent($school);
        $admin = $this->makeSchoolAdmin($school);

        Enrollment::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'class_arm_id' => $armA->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);

        $this->actingAs($admin)->postJson('/api/enrollments', [
            'student_id'          => $student->id,
            'class_arm_id'        => $armB->id,
            'academic_session_id' => $session->id,
        ])->assertStatus(422);
    }

    public function test_moving_a_student_to_a_different_arm_is_an_update_not_a_new_enrollment(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $this->makeSession($school);
        $armA = $this->makeArm($school, 'JSS 1');
        $armB = $this->makeArm($school, 'JSS 2');
        $student = $this->makeStudent($school);
        $admin = $this->makeSchoolAdmin($school);

        $enrollment = Enrollment::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'class_arm_id' => $armA->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/enrollments/{$enrollment->id}", [
            'class_arm_id' => $armB->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.class_arm.full_name', 'JSS 2A');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_cannot_change_student_or_session_on_update(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $this->makeSession($school);
        $arm = $this->makeArm($school);
        $student = $this->makeStudent($school);
        $otherStudent = $this->makeStudent($school, ['admission_number' => 'ADM-OTHER']);
        $admin = $this->makeSchoolAdmin($school);

        $enrollment = Enrollment::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);

        $this->actingAs($admin)->putJson("/api/enrollments/{$enrollment->id}", [
            'student_id' => $otherStudent->id,
        ])->assertStatus(422);
    }

    public function test_status_can_be_updated_at_end_of_session(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $this->makeSession($school);
        $arm = $this->makeArm($school);
        $student = $this->makeStudent($school);
        $admin = $this->makeSchoolAdmin($school);

        $enrollment = Enrollment::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);

        $this->actingAs($admin)->putJson("/api/enrollments/{$enrollment->id}", ['status' => 'promoted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'promoted');
    }

    public function test_index_filters_by_current_session_and_class_arm(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $currentSession = $this->makeSession($school, ['name' => '2025/2026', 'is_current' => true]);
        $pastSession = $this->makeSession($school, ['name' => '2024/2025', 'is_current' => false]);
        $arm = $this->makeArm($school);
        $studentA = $this->makeStudent($school, ['admission_number' => 'ADM-A']);
        $studentB = $this->makeStudent($school, ['admission_number' => 'ADM-B']);

        Enrollment::create([
            'school_id' => $school->id, 'student_id' => $studentA->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $currentSession->id, 'status' => 'active',
        ]);
        Enrollment::create([
            'school_id' => $school->id, 'student_id' => $studentB->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $pastSession->id, 'status' => 'promoted',
        ]);

        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->getJson('/api/enrollments?current=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.student.admission_number', 'ADM-A');
    }

    public function test_tenant_isolation(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        $sessionB = $this->makeSession($schoolB);
        $armB = $this->makeArm($schoolB);
        $studentB = $this->makeStudent($schoolB);

        $foreignEnrollment = Enrollment::create([
            'school_id' => $schoolB->id, 'student_id' => $studentB->id, 'class_arm_id' => $armB->id,
            'academic_session_id' => $sessionB->id, 'status' => 'active',
        ]);

        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/enrollments/{$foreignEnrollment->id}")->assertNotFound();
    }

    public function test_guardian_can_only_view_enrollments_of_their_own_linked_students(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $this->makeSession($school);
        $arm = $this->makeArm($school);
        $ownStudent = $this->makeStudent($school, ['admission_number' => 'ADM-OWN']);
        $otherStudent = $this->makeStudent($school, ['admission_number' => 'ADM-OTHER']);

        $ownEnrollment = Enrollment::create([
            'school_id' => $school->id, 'student_id' => $ownStudent->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);
        $otherEnrollment = Enrollment::create([
            'school_id' => $school->id, 'student_id' => $otherStudent->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $session->id, 'status' => 'active',
        ]);

        $guardianUser = User::create([
            'school_id' => $school->id, 'name' => 'Parent', 'email' => 'parent@example.com', 'password' => 'secret',
        ]);
        $guardian = GuardianModel::create([
            'school_id' => $school->id, 'user_id' => $guardianUser->id,
            'first_name' => 'Parent', 'last_name' => 'One', 'phone' => '08000000000',
        ]);
        $guardian->students()->attach($ownStudent->id, ['relationship' => 'Mother', 'is_primary_contact' => true]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $school->id]));

        $this->actingAs($guardianUser)->getJson("/api/enrollments/{$ownEnrollment->id}")->assertOk();
        $this->actingAs($guardianUser)->getJson("/api/enrollments/{$otherEnrollment->id}")->assertForbidden();
    }

    public function test_teacher_cannot_create_enrollments(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = $this->makeSession($school);
        $arm = $this->makeArm($school);
        $student = $this->makeStudent($school);

        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher@example.com', 'password' => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $teacher->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web', 'school_id' => $school->id]));

        $this->actingAs($teacher)->getJson('/api/enrollments')->assertOk();
        $this->actingAs($teacher)->postJson('/api/enrollments', [
            'student_id' => $student->id, 'class_arm_id' => $arm->id, 'academic_session_id' => $session->id,
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/enrollments')->assertUnauthorized();
    }
}
