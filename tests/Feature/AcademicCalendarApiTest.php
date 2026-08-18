<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Student;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AcademicCalendarApiTest extends TestCase
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

    // --- Academic Sessions ---

    public function test_school_admin_can_create_an_academic_session(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/academic-sessions', [
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_current', true);
    }

    public function test_setting_a_session_current_unsets_the_previous_current_session(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $old = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2024/2025', 'start_date' => '2024-09-01', 'end_date' => '2025-07-31', 'is_current' => true,
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/academic-sessions', [
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('academic_sessions', ['id' => $old->id, 'is_current' => false]);
    }

    public function test_session_end_date_must_be_after_start_date(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/academic-sessions', [
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2024-01-01',
        ])->assertStatus(422);
    }

    public function test_session_name_must_be_unique_within_a_school_but_not_across_schools(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        AcademicSession::create([
            'school_id' => $schoolA->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        $adminA = $this->makeSchoolAdmin($schoolA);
        $adminB = $this->makeSchoolAdmin($schoolB);

        $this->actingAs($adminA)->postJson('/api/academic-sessions', [
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ])->assertStatus(422);

        $this->actingAs($adminB)->postJson('/api/academic-sessions', [
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ])->assertCreated();
    }

    public function test_teacher_can_view_but_not_create_sessions(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        $teacher = $this->makeTeacherUser($school);

        $this->actingAs($teacher)->getJson('/api/academic-sessions')->assertOk();
        $this->actingAs($teacher)->getJson("/api/academic-sessions/{$session->id}")->assertOk();
        $this->actingAs($teacher)->postJson('/api/academic-sessions', [
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
        ])->assertForbidden();
    }

    public function test_cannot_delete_a_session_with_enrollments(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
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

        $this->actingAs($admin)->deleteJson("/api/academic-sessions/{$session->id}")->assertStatus(409);
    }

    public function test_tenant_isolation_for_sessions(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        $foreignSession = AcademicSession::create([
            'school_id' => $schoolB->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/academic-sessions/{$foreignSession->id}")->assertNotFound();
    }

    // --- Terms ---

    public function test_school_admin_can_create_a_term(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/terms', [
            'academic_session_id' => $session->id, 'name' => 'First Term', 'sequence' => 1,
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.sequence', 1);
    }

    public function test_setting_a_term_current_unsets_the_previous_current_term(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        $first = Term::create([
            'school_id' => $school->id, 'academic_session_id' => $session->id, 'name' => 'First Term', 'sequence' => 1,
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/terms', [
            'academic_session_id' => $session->id, 'name' => 'Second Term', 'sequence' => 2,
            'start_date' => '2026-01-05', 'end_date' => '2026-04-01', 'is_current' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('terms', ['id' => $first->id, 'is_current' => false]);
    }

    public function test_term_sequence_must_be_unique_within_a_session(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $session = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        Term::create([
            'school_id' => $school->id, 'academic_session_id' => $session->id, 'name' => 'First Term', 'sequence' => 1,
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15',
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/terms', [
            'academic_session_id' => $session->id, 'name' => 'Duplicate', 'sequence' => 1,
            'start_date' => '2026-01-05', 'end_date' => '2026-04-01',
        ])->assertStatus(422);
    }

    public function test_cannot_change_a_terms_session_on_update(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $sessionA = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        $sessionB = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
        ]);
        $term = Term::create([
            'school_id' => $school->id, 'academic_session_id' => $sessionA->id, 'name' => 'First Term', 'sequence' => 1,
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15',
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->putJson("/api/terms/{$term->id}", [
            'academic_session_id' => $sessionB->id,
        ])->assertStatus(422);
    }

    public function test_index_filters_terms_by_session(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $sessionA = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
        ]);
        $sessionB = AcademicSession::create([
            'school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
        ]);
        Term::create([
            'school_id' => $school->id, 'academic_session_id' => $sessionA->id, 'name' => 'First Term', 'sequence' => 1,
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15',
        ]);
        Term::create([
            'school_id' => $school->id, 'academic_session_id' => $sessionB->id, 'name' => 'First Term', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2026-12-15',
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->getJson("/api/terms?academic_session_id={$sessionA->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/academic-sessions')->assertUnauthorized();
        $this->getJson('/api/terms')->assertUnauthorized();
    }
}
