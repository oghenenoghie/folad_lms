<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\Guardian as GuardianModel;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;
    protected AcademicSession $session;
    protected ClassLevel $level;
    protected ClassArm $arm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $this->session = $this->school->academicSessions()->create([
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ]);
        $this->level = ClassLevel::create(['school_id' => $this->school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $this->arm = ClassArm::create(['school_id' => $this->school->id, 'class_level_id' => $this->level->id, 'name' => 'A']);
    }

    protected function makeSchoolAdmin(): User
    {
        $user = User::create([
            'school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'admin'.random_int(1000, 9999).'@example.com', 'password' => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user->assignRole(Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web', 'school_id' => $this->school->id]));

        return $user;
    }

    protected function makeFormTeacher(): array
    {
        $user = User::create([
            'school_id' => $this->school->id, 'name' => 'Form Teacher', 'email' => 'ft'.random_int(1000, 9999).'@example.com', 'password' => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web', 'school_id' => $this->school->id]));
        $staff = Staff::create(['school_id' => $this->school->id, 'staff_number' => 'STF-'.random_int(1000, 9999), 'first_name' => 'Form', 'last_name' => 'Teacher', 'user_id' => $user->id]);
        $this->arm->update(['form_teacher_id' => $staff->id]);

        return [$user, $staff];
    }

    protected function makeOtherTeacher(): User
    {
        $user = User::create([
            'school_id' => $this->school->id, 'name' => 'Other Teacher', 'email' => 'ot'.random_int(1000, 9999).'@example.com', 'password' => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web', 'school_id' => $this->school->id]));
        Staff::create(['school_id' => $this->school->id, 'staff_number' => 'STF-'.random_int(1000, 9999), 'first_name' => 'Other', 'last_name' => 'Teacher', 'user_id' => $user->id]);

        return $user;
    }

    protected function makeEnrollment(string $admissionNumber): Enrollment
    {
        $student = Student::create([
            'school_id' => $this->school->id, 'admission_number' => $admissionNumber, 'first_name' => 'Student',
            'last_name' => $admissionNumber, 'gender' => 'female', 'date_of_birth' => '2012-01-01',
        ]);

        return Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id, 'class_arm_id' => $this->arm->id,
            'academic_session_id' => $this->session->id, 'status' => 'active',
        ]);
    }

    public function test_form_teacher_can_bulk_mark_their_own_class(): void
    {
        [$teacherUser, $staff] = $this->makeFormTeacher();
        $e1 = $this->makeEnrollment('ADM-1');
        $e2 = $this->makeEnrollment('ADM-2');

        $response = $this->actingAs($teacherUser)->postJson('/api/attendances/bulk-mark', [
            'class_arm_id' => $this->arm->id,
            'date'         => '2025-10-01',
            'records'      => [
                ['enrollment_id' => $e1->id, 'status' => 'present'],
                ['enrollment_id' => $e2->id, 'status' => 'absent', 'remarks' => 'Sick'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $e1->id, 'date' => '2025-10-01', 'status' => 'present', 'recorded_by' => $staff->id]);
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $e2->id, 'date' => '2025-10-01', 'status' => 'absent', 'remarks' => 'Sick']);
    }

    public function test_a_teacher_who_is_not_the_form_teacher_cannot_mark_this_class(): void
    {
        $this->makeFormTeacher();
        $otherTeacher = $this->makeOtherTeacher();
        $e1 = $this->makeEnrollment('ADM-1');

        $this->actingAs($otherTeacher)->postJson('/api/attendances/bulk-mark', [
            'class_arm_id' => $this->arm->id,
            'date'         => '2025-10-01',
            'records'      => [['enrollment_id' => $e1->id, 'status' => 'present']],
        ])->assertForbidden();
    }

    public function test_school_admin_can_mark_any_class(): void
    {
        $admin = $this->makeSchoolAdmin();
        $e1 = $this->makeEnrollment('ADM-1');

        $this->actingAs($admin)->postJson('/api/attendances/bulk-mark', [
            'class_arm_id' => $this->arm->id,
            'date'         => '2025-10-01',
            'records'      => [['enrollment_id' => $e1->id, 'status' => 'present']],
        ])->assertOk();
    }

    public function test_bulk_marking_the_same_day_again_updates_rather_than_duplicates(): void
    {
        [$teacherUser] = $this->makeFormTeacher();
        $e1 = $this->makeEnrollment('ADM-1');

        $this->actingAs($teacherUser)->postJson('/api/attendances/bulk-mark', [
            'class_arm_id' => $this->arm->id, 'date' => '2025-10-01',
            'records' => [['enrollment_id' => $e1->id, 'status' => 'absent']],
        ])->assertOk();

        $this->actingAs($teacherUser)->postJson('/api/attendances/bulk-mark', [
            'class_arm_id' => $this->arm->id, 'date' => '2025-10-01',
            'records' => [['enrollment_id' => $e1->id, 'status' => 'late']],
        ])->assertOk();

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $e1->id, 'date' => '2025-10-01', 'status' => 'late']);
    }

    public function test_rejects_an_enrollment_not_in_the_given_class_arm(): void
    {
        [$teacherUser] = $this->makeFormTeacher();
        $otherArm = ClassArm::create(['school_id' => $this->school->id, 'class_level_id' => $this->level->id, 'name' => 'B']);
        $student = Student::create([
            'school_id' => $this->school->id, 'admission_number' => 'ADM-X', 'first_name' => 'X', 'last_name' => 'Y',
            'gender' => 'male', 'date_of_birth' => '2012-01-01',
        ]);
        $foreignEnrollment = Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id, 'class_arm_id' => $otherArm->id,
            'academic_session_id' => $this->session->id, 'status' => 'active',
        ]);

        $this->actingAs($teacherUser)->postJson('/api/attendances/bulk-mark', [
            'class_arm_id' => $this->arm->id, 'date' => '2025-10-01',
            'records' => [['enrollment_id' => $foreignEnrollment->id, 'status' => 'present']],
        ])->assertStatus(422);
    }

    public function test_cannot_mark_a_future_date(): void
    {
        $admin = $this->makeSchoolAdmin();
        $e1 = $this->makeEnrollment('ADM-1');

        $this->actingAs($admin)->postJson('/api/attendances/bulk-mark', [
            'class_arm_id' => $this->arm->id,
            'date'         => now()->addDay()->toDateString(),
            'records'      => [['enrollment_id' => $e1->id, 'status' => 'present']],
        ])->assertStatus(422);
    }

    public function test_cannot_change_identity_fields_on_update(): void
    {
        [$teacherUser] = $this->makeFormTeacher();
        $e1 = $this->makeEnrollment('ADM-1');
        $attendance = Attendance::create(['school_id' => $this->school->id, 'enrollment_id' => $e1->id, 'date' => '2025-10-01', 'status' => 'present']);

        $this->actingAs($teacherUser)->putJson("/api/attendances/{$attendance->id}", [
            'date' => '2025-10-02',
        ])->assertStatus(422);
    }

    public function test_only_school_admin_can_delete_a_record(): void
    {
        [$teacherUser] = $this->makeFormTeacher();
        $admin = $this->makeSchoolAdmin();
        $e1 = $this->makeEnrollment('ADM-1');
        $attendance = Attendance::create(['school_id' => $this->school->id, 'enrollment_id' => $e1->id, 'date' => '2025-10-01', 'status' => 'present']);

        $this->actingAs($teacherUser)->deleteJson("/api/attendances/{$attendance->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/attendances/{$attendance->id}")->assertNoContent();
        $this->assertSoftDeleted('attendances', ['id' => $attendance->id]);
    }

    public function test_summary_computes_attendance_rate_treating_late_as_attended(): void
    {
        $admin = $this->makeSchoolAdmin();
        $e1 = $this->makeEnrollment('ADM-1');

        Attendance::create(['school_id' => $this->school->id, 'enrollment_id' => $e1->id, 'date' => '2025-10-01', 'status' => 'present']);
        Attendance::create(['school_id' => $this->school->id, 'enrollment_id' => $e1->id, 'date' => '2025-10-02', 'status' => 'absent']);
        Attendance::create(['school_id' => $this->school->id, 'enrollment_id' => $e1->id, 'date' => '2025-10-03', 'status' => 'late']);
        Attendance::create(['school_id' => $this->school->id, 'enrollment_id' => $e1->id, 'date' => '2025-10-04', 'status' => 'excused']);

        $response = $this->actingAs($admin)->getJson("/api/attendances/summary?enrollment_id={$e1->id}&from=2025-10-01&to=2025-10-04");

        $response->assertOk();
        $response->assertJsonPath('data.total_days', 4);
        $response->assertJsonPath('data.present', 1);
        $response->assertJsonPath('data.absent', 1);
        $response->assertJsonPath('data.late', 1);
        $response->assertJsonPath('data.excused', 1);
        $response->assertJsonPath('data.attendance_rate', 0.5);
    }

    public function test_guardian_can_view_summary_of_their_own_linked_student_but_not_another(): void
    {
        $e1 = $this->makeEnrollment('ADM-1');
        $e2 = $this->makeEnrollment('ADM-2');
        Attendance::create(['school_id' => $this->school->id, 'enrollment_id' => $e1->id, 'date' => '2025-10-01', 'status' => 'present']);

        $guardianUser = User::create([
            'school_id' => $this->school->id, 'name' => 'Parent', 'email' => 'parent@example.com', 'password' => 'secret',
        ]);
        $guardian = GuardianModel::create([
            'school_id' => $this->school->id, 'user_id' => $guardianUser->id, 'first_name' => 'Parent', 'last_name' => 'One', 'phone' => '08000000000',
        ]);
        $guardian->students()->attach($e1->student_id, ['relationship' => 'Mother', 'is_primary_contact' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $this->school->id]));

        $this->actingAs($guardianUser)->getJson("/api/attendances/summary?enrollment_id={$e1->id}&from=2025-10-01&to=2025-10-01")->assertOk();
        $this->actingAs($guardianUser)->getJson("/api/attendances/summary?enrollment_id={$e2->id}&from=2025-10-01&to=2025-10-01")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/attendances')->assertUnauthorized();
    }
}
