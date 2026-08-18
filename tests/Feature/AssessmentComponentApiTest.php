<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AssessmentComponentApiTest extends TestCase
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

    protected function makeTerm(School $school): Term
    {
        $session = $school->academicSessions()->create([
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true,
        ]);

        return Term::create([
            'school_id' => $school->id, 'academic_session_id' => $session->id, 'name' => 'First Term',
            'sequence' => 1, 'start_date' => '2025-09-01', 'end_date' => '2025-12-15',
        ]);
    }

    public function test_school_admin_can_create_an_assessment_component(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $term = $this->makeTerm($school);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/assessment-components', [
            'term_id' => $term->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.max_score', '20.00');
    }

    public function test_component_name_must_be_unique_within_a_term_but_not_across_terms(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $term = $this->makeTerm($school);
        $otherTerm = Term::create([
            'school_id' => $school->id, 'academic_session_id' => $term->academic_session_id, 'name' => 'Second Term',
            'sequence' => 2, 'start_date' => '2026-01-05', 'end_date' => '2026-04-01',
        ]);
        AssessmentComponent::create(['school_id' => $school->id, 'term_id' => $term->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/assessment-components', [
            'term_id' => $term->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 2,
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/assessment-components', [
            'term_id' => $otherTerm->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1,
        ])->assertCreated();
    }

    public function test_cannot_change_a_components_term_on_update(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $term = $this->makeTerm($school);
        $otherTerm = Term::create([
            'school_id' => $school->id, 'academic_session_id' => $term->academic_session_id, 'name' => 'Second Term',
            'sequence' => 2, 'start_date' => '2026-01-05', 'end_date' => '2026-04-01',
        ]);
        $component = AssessmentComponent::create(['school_id' => $school->id, 'term_id' => $term->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->putJson("/api/assessment-components/{$component->id}", [
            'term_id' => $otherTerm->id,
        ])->assertStatus(422);
    }

    public function test_index_filters_by_term(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $term = $this->makeTerm($school);
        $otherTerm = Term::create([
            'school_id' => $school->id, 'academic_session_id' => $term->academic_session_id, 'name' => 'Second Term',
            'sequence' => 2, 'start_date' => '2026-01-05', 'end_date' => '2026-04-01',
        ]);
        AssessmentComponent::create(['school_id' => $school->id, 'term_id' => $term->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1]);
        AssessmentComponent::create(['school_id' => $school->id, 'term_id' => $otherTerm->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1]);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->getJson("/api/assessment-components?term_id={$term->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_cannot_delete_a_component_with_recorded_results(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $term = $this->makeTerm($school);
        $component = AssessmentComponent::create(['school_id' => $school->id, 'term_id' => $term->id, 'name' => '1st CA', 'max_score' => 20, 'sequence' => 1]);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $arm = ClassArm::create(['school_id' => $school->id, 'class_level_id' => $level->id, 'name' => 'A']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $student = Student::create([
            'school_id' => $school->id, 'admission_number' => 'ADM-1', 'first_name' => 'Ada', 'last_name' => 'Okafor',
            'gender' => 'female', 'date_of_birth' => '2012-01-01',
        ]);
        $enrollment = Enrollment::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'class_arm_id' => $arm->id,
            'academic_session_id' => $term->academic_session_id, 'status' => 'active',
        ]);
        $enrollment->results()->create([
            'school_id' => $school->id, 'subject_id' => $subject->id, 'assessment_component_id' => $component->id, 'score' => 18,
        ]);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->deleteJson("/api/assessment-components/{$component->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('assessment_components', ['id' => $component->id]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/assessment-components')->assertUnauthorized();
    }
}
