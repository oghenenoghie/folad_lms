<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GradingScaleApiTest extends TestCase
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

    public function test_school_admin_can_create_a_grading_scale(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/grading-scales', [
            'name' => 'WAEC Standard', 'is_default' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_default', true);
    }

    public function test_setting_a_scale_default_unsets_the_previous_default_in_the_same_slot(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $old = GradingScale::create(['school_id' => $school->id, 'name' => 'Old Scale', 'is_default' => true]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/grading-scales', [
            'name' => 'New Scale', 'is_default' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('grading_scales', ['id' => $old->id, 'is_default' => false]);
    }

    public function test_a_class_level_specific_default_does_not_unset_the_school_wide_default(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $level = ClassLevel::create(['school_id' => $school->id, 'name' => 'SS 1', 'rank' => 4]);
        $schoolWide = GradingScale::create(['school_id' => $school->id, 'name' => 'School Wide', 'is_default' => true]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/grading-scales', [
            'name' => 'SS Scale', 'class_level_id' => $level->id, 'is_default' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('grading_scales', ['id' => $schoolWide->id, 'is_default' => true]);
    }

    public function test_school_admin_can_sync_bands_for_a_scale(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $scale = GradingScale::create(['school_id' => $school->id, 'name' => 'WAEC Standard']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->putJson("/api/grading-scales/{$scale->id}/bands", [
            'bands' => [
                ['grade' => 'A1', 'min_score' => 75, 'max_score' => 100, 'remark' => 'Excellent'],
                ['grade' => 'B2', 'min_score' => 70, 'max_score' => 74.99, 'remark' => 'Very Good'],
                ['grade' => 'F9', 'min_score' => 0, 'max_score' => 39.99, 'remark' => 'Fail'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(3, 'data.bands');
        $this->assertDatabaseHas('grading_scale_bands', ['grading_scale_id' => $scale->id, 'grade' => 'A1']);
    }

    public function test_syncing_bands_replaces_the_previous_set(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $scale = GradingScale::create(['school_id' => $school->id, 'name' => 'WAEC Standard']);
        $scale->bands()->create(['school_id' => $school->id, 'grade' => 'OLD', 'min_score' => 0, 'max_score' => 100]);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->putJson("/api/grading-scales/{$scale->id}/bands", [
            'bands' => [['grade' => 'A1', 'min_score' => 75, 'max_score' => 100]],
        ])->assertOk();

        $this->assertDatabaseMissing('grading_scale_bands', ['grading_scale_id' => $scale->id, 'grade' => 'OLD']);
        $this->assertDatabaseHas('grading_scale_bands', ['grading_scale_id' => $scale->id, 'grade' => 'A1']);
    }

    public function test_sync_rejects_overlapping_bands(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $scale = GradingScale::create(['school_id' => $school->id, 'name' => 'WAEC Standard']);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->putJson("/api/grading-scales/{$scale->id}/bands", [
            'bands' => [
                ['grade' => 'A1', 'min_score' => 70, 'max_score' => 100],
                ['grade' => 'B2', 'min_score' => 65, 'max_score' => 74],
            ],
        ])->assertStatus(422);
    }

    public function test_sync_rejects_duplicate_grade_labels(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $scale = GradingScale::create(['school_id' => $school->id, 'name' => 'WAEC Standard']);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->putJson("/api/grading-scales/{$scale->id}/bands", [
            'bands' => [
                ['grade' => 'A1', 'min_score' => 75, 'max_score' => 100],
                ['grade' => 'A1', 'min_score' => 50, 'max_score' => 74],
            ],
        ])->assertStatus(422);
    }

    public function test_tenant_isolation(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);
        $foreignScale = GradingScale::create(['school_id' => $schoolB->id, 'name' => 'Foreign Scale']);
        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/grading-scales/{$foreignScale->id}")->assertNotFound();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/grading-scales')->assertUnauthorized();
    }
}
