<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SubjectApiTest extends TestCase
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

    public function test_school_admin_can_create_a_subject(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/subjects', [
            'name' => 'Mathematics', 'code' => 'MTH', 'is_core' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('subjects', ['name' => 'Mathematics', 'school_id' => $school->id]);
    }

    public function test_subject_name_must_be_unique_within_a_school_but_not_across_schools(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        Subject::create(['school_id' => $schoolA->id, 'name' => 'Mathematics']);
        $adminA = $this->makeSchoolAdmin($schoolA);
        $adminB = $this->makeSchoolAdmin($schoolB);

        $this->actingAs($adminA)->postJson('/api/subjects', ['name' => 'Mathematics'])->assertStatus(422);
        $this->actingAs($adminB)->postJson('/api/subjects', ['name' => 'Mathematics'])->assertCreated();
    }

    public function test_teacher_can_view_but_not_create_subjects(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $teacher = $this->makeTeacherUser($school);

        $this->actingAs($teacher)->getJson('/api/subjects')->assertOk();
        $this->actingAs($teacher)->getJson("/api/subjects/{$subject->id}")->assertOk();
        $this->actingAs($teacher)->postJson('/api/subjects', ['name' => 'Physics'])->assertForbidden();
    }

    public function test_tenant_isolation(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        $foreignSubject = Subject::create(['school_id' => $schoolB->id, 'name' => 'Mathematics']);
        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/subjects/{$foreignSubject->id}")->assertNotFound();
    }

    public function test_school_admin_can_sync_class_levels_for_a_subject(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $jss1 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $jss2 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 2', 'rank' => 2]);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->putJson("/api/subjects/{$subject->id}/class-levels", [
            'class_level_ids' => [$jss1->id, $jss2->id],
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data.class_levels');
        $this->assertDatabaseHas('class_subject', ['subject_id' => $subject->id, 'class_level_id' => $jss1->id]);
        $this->assertDatabaseHas('class_subject', ['subject_id' => $subject->id, 'class_level_id' => $jss2->id]);
    }

    public function test_syncing_class_levels_replaces_the_previous_set(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $jss1 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $jss2 = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 2', 'rank' => 2]);
        $subject->classLevels()->attach($jss1->id);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->putJson("/api/subjects/{$subject->id}/class-levels", [
            'class_level_ids' => [$jss2->id],
        ])->assertOk();

        $this->assertDatabaseMissing('class_subject', ['subject_id' => $subject->id, 'class_level_id' => $jss1->id]);
        $this->assertDatabaseHas('class_subject', ['subject_id' => $subject->id, 'class_level_id' => $jss2->id]);
    }

    public function test_sync_rejects_a_class_level_from_another_school(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        $subject = Subject::create(['school_id' => $schoolA->id, 'name' => 'Mathematics']);
        $foreignLevel = ClassLevel::create(['school_id' => $schoolB->id, 'name' => 'JSS 1', 'rank' => 1]);
        $admin = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($admin)->putJson("/api/subjects/{$subject->id}/class-levels", [
            'class_level_ids' => [$foreignLevel->id],
        ])->assertStatus(422);
    }

    public function test_index_filters_by_core_only_and_class_level(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'JSS 1', 'rank' => 1]);
        $core = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'is_core' => true]);
        $elective = Subject::create(['school_id' => $school->id, 'name' => 'Music', 'is_core' => false]);
        $core->classLevels()->attach($level->id);
        $admin = $this->makeSchoolAdmin($school);

        $coreResponse = $this->actingAs($admin)->getJson('/api/subjects?core_only=1');
        $coreResponse->assertJsonCount(1, 'data');
        $coreResponse->assertJsonPath('data.0.name', 'Mathematics');

        $levelResponse = $this->actingAs($admin)->getJson("/api/subjects?class_level_id={$level->id}");
        $levelResponse->assertJsonCount(1, 'data');
        $levelResponse->assertJsonPath('data.0.name', 'Mathematics');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/subjects')->assertUnauthorized();
    }
}
