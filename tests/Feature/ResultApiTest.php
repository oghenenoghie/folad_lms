<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\GradingScale;
use App\Models\Guardian as GuardianModel;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResultApiTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;
    protected AcademicSession $session;
    protected Term $term;
    protected ClassLevel $level;
    protected ClassArm $arm;
    protected Subject $subject;
    protected AssessmentComponent $ca1;
    protected AssessmentComponent $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $this->session = $this->school->academicSessions()->create([
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ]);
        $this->term = Term::create([
            'school_id' => $this->school->id, 'academic_session_id' => $this->session->id, 'name' => 'First Term',
            'sequence' => 1, 'start_date' => '2025-09-01', 'end_date' => '2025-12-15',
        ]);
        $this->level = ClassLevel::create(['school_id' => $this->school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $this->arm = ClassArm::create(['school_id' => $this->school->id, 'class_level_id' => $this->level->id, 'name' => 'A']);
        $this->subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics']);
        $this->level->subjects()->attach($this->subject->id);
        $this->ca1 = AssessmentComponent::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1]);
        $this->exam = AssessmentComponent::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'Exam', 'max_score' => 80, 'sequence' => 2]);
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

    protected function makeTeacherUser(): User
    {
        $user = User::create([
            'school_id' => $this->school->id, 'name' => 'Teacher', 'email' => 'teacher'.random_int(1000, 9999).'@example.com', 'password' => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web', 'school_id' => $this->school->id]));

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

    public function test_teacher_can_record_a_score(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');
        $teacher = $this->makeTeacherUser();

        $response = $this->actingAs($teacher)->postJson('/api/results', [
            'enrollment_id' => $enrollment->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 18,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.score', '18.00');
    }

    public function test_score_cannot_exceed_the_components_max_score(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');
        $teacher = $this->makeTeacherUser();

        $this->actingAs($teacher)->postJson('/api/results', [
            'enrollment_id' => $enrollment->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 25,
        ])->assertStatus(422);
    }

    public function test_duplicate_score_for_the_same_enrollment_subject_component_is_rejected(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');
        $teacher = $this->makeTeacherUser();

        $enrollment->results()->create([
            'school_id' => $this->school->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 15,
        ]);

        $this->actingAs($teacher)->postJson('/api/results', [
            'enrollment_id' => $enrollment->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 18,
        ])->assertStatus(422);
    }

    public function test_recorded_by_is_set_from_the_authenticated_teachers_own_staff_record_not_client_input(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');
        $teacherUser = $this->makeTeacherUser();
        $staff = Staff::create([
            'school_id' => $this->school->id, 'staff_number' => 'STF-1', 'first_name' => 'Tunde', 'last_name' => 'Bello',
            'user_id' => $teacherUser->id,
        ]);
        $otherStaff = Staff::create(['school_id' => $this->school->id, 'staff_number' => 'STF-2', 'first_name' => 'Other', 'last_name' => 'Teacher']);

        $response = $this->actingAs($teacherUser)->postJson('/api/results', [
            'enrollment_id' => $enrollment->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 18,
            'recorded_by' => $otherStaff->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('results', [
            'enrollment_id' => $enrollment->id, 'assessment_component_id' => $this->ca1->id, 'recorded_by' => $staff->id,
        ]);
    }

    public function test_cannot_change_identity_fields_on_update(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');
        $otherSubject = Subject::create(['school_id' => $this->school->id, 'name' => 'English']);
        $teacher = $this->makeTeacherUser();
        $result = $enrollment->results()->create([
            'school_id' => $this->school->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 15,
        ]);

        $this->actingAs($teacher)->putJson("/api/results/{$result->id}", [
            'subject_id' => $otherSubject->id,
        ])->assertStatus(422);
    }

    public function test_only_school_admin_can_delete_a_result(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');
        $teacher = $this->makeTeacherUser();
        $admin = $this->makeSchoolAdmin();
        $result = $enrollment->results()->create([
            'school_id' => $this->school->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 15,
        ]);

        $this->actingAs($teacher)->deleteJson("/api/results/{$result->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/results/{$result->id}")->assertNoContent();
        $this->assertSoftDeleted('results', ['id' => $result->id]);
    }

    public function test_guardian_can_view_but_not_record_results_for_their_own_linked_student(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');
        $result = $enrollment->results()->create([
            'school_id' => $this->school->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->ca1->id, 'score' => 15,
        ]);

        $guardianUser = User::create([
            'school_id' => $this->school->id, 'name' => 'Parent', 'email' => 'parent@example.com', 'password' => 'secret',
        ]);
        $guardian = GuardianModel::create([
            'school_id' => $this->school->id, 'user_id' => $guardianUser->id, 'first_name' => 'Parent', 'last_name' => 'One', 'phone' => '08000000000',
        ]);
        $guardian->students()->attach($enrollment->student_id, ['relationship' => 'Mother', 'is_primary_contact' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $this->school->id]));

        $this->actingAs($guardianUser)->getJson("/api/results/{$result->id}")->assertOk();
        $this->actingAs($guardianUser)->postJson('/api/results', [
            'enrollment_id' => $enrollment->id, 'subject_id' => $this->subject->id,
            'assessment_component_id' => $this->exam->id, 'score' => 60,
        ])->assertForbidden();
    }

    public function test_report_computes_total_grade_and_position(): void
    {
        $topEnrollment = $this->makeEnrollment('ADM-TOP');
        $midEnrollment = $this->makeEnrollment('ADM-MID');
        $lastEnrollment = $this->makeEnrollment('ADM-LAST');

        $scale = GradingScale::create(['school_id' => $this->school->id, 'name' => 'WAEC Standard', 'is_default' => true]);
        $scale->bands()->createMany([
            ['school_id' => $this->school->id, 'grade' => 'A1', 'min_score' => 75, 'max_score' => 100, 'remark' => 'Excellent'],
            ['school_id' => $this->school->id, 'grade' => 'B2', 'min_score' => 60, 'max_score' => 74.99, 'remark' => 'Good'],
            ['school_id' => $this->school->id, 'grade' => 'F9', 'min_score' => 0, 'max_score' => 39.99, 'remark' => 'Fail'],
        ]);

        // Top: 18 + 70 = 88 -> A1
        $topEnrollment->results()->create(['school_id' => $this->school->id, 'subject_id' => $this->subject->id, 'assessment_component_id' => $this->ca1->id, 'score' => 18]);
        $topEnrollment->results()->create(['school_id' => $this->school->id, 'subject_id' => $this->subject->id, 'assessment_component_id' => $this->exam->id, 'score' => 70]);

        // Mid: 12 + 50 = 62 -> B2
        $midEnrollment->results()->create(['school_id' => $this->school->id, 'subject_id' => $this->subject->id, 'assessment_component_id' => $this->ca1->id, 'score' => 12]);
        $midEnrollment->results()->create(['school_id' => $this->school->id, 'subject_id' => $this->subject->id, 'assessment_component_id' => $this->exam->id, 'score' => 50]);

        // Last has no scores recorded yet -> defaults to 0 -> F9, still counted in class_size/ranking

        $admin = $this->makeSchoolAdmin();

        $response = $this->actingAs($admin)->getJson("/api/results/report?enrollment_id={$midEnrollment->id}&term_id={$this->term->id}");

        $response->assertOk();
        $subjectReport = collect($response->json('data.subjects'))->firstWhere('subject_id', $this->subject->id);

        $this->assertEquals(62, $subjectReport['total']);
        $this->assertEquals(100, $subjectReport['max_total']);
        $this->assertEquals('B2', $subjectReport['grade']);
        $this->assertEquals(2, $subjectReport['position']);
        $this->assertEquals(3, $subjectReport['class_size']);
    }

    public function test_report_requires_view_access_to_the_enrollment(): void
    {
        $enrollment = $this->makeEnrollment('ADM-1');

        $otherSchool = School::create(['name' => 'School B', 'code' => 'school-b']);
        $otherAdmin = User::create([
            'school_id' => $otherSchool->id, 'name' => 'Other Admin', 'email' => 'other@example.com', 'password' => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($otherSchool->id);
        $otherAdmin->assignRole(Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web', 'school_id' => $otherSchool->id]));

        $this->actingAs($otherAdmin)
            ->getJson("/api/results/report?enrollment_id={$enrollment->id}&term_id={$this->term->id}")
            ->assertNotFound();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/results')->assertUnauthorized();
    }
}
