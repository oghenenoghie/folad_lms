<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SchoolApiTest extends TestCase
{
    use RefreshDatabase;

    /** super_admin-ness is purely school_id === null (see User::isSuperAdmin) -- no role needed. */
    protected function makeSuperAdmin(): User
    {
        return User::create([
            'school_id' => null,
            'name'      => 'Platform Owner',
            'email'     => 'owner'.random_int(1000, 9999).'@example.com',
            'password'  => 'secret',
        ]);
    }

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

    public function test_super_admin_can_list_all_schools(): void
    {
        School::create(['name' => 'School A', 'code' => 'school-a']);
        School::create(['name' => 'School B', 'code' => 'school-b']);
        $superAdmin = $this->makeSuperAdmin();

        $response = $this->actingAs($superAdmin)->getJson('/api/schools');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_school_admin_cannot_list_schools(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->getJson('/api/schools')->assertForbidden();
    }

    public function test_super_admin_can_create_a_school(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $response = $this->actingAs($superAdmin)->postJson('/api/schools', [
            'name' => 'Greenfield College',
            'code' => 'greenfield',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('schools', ['code' => 'greenfield']);
    }

    public function test_school_admin_cannot_create_a_school(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->postJson('/api/schools', [
            'name' => 'New School', 'code' => 'new-school',
        ])->assertForbidden();
    }

    public function test_school_code_must_be_globally_unique(): void
    {
        School::create(['name' => 'School A', 'code' => 'greenfield']);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)->postJson('/api/schools', [
            'name' => 'Another School', 'code' => 'greenfield',
        ])->assertStatus(422);
    }

    public function test_school_admin_can_view_their_own_school_but_not_another(): void
    {
        $ownSchool = School::create(['name' => 'School A', 'code' => 'school-a']);
        $otherSchool = School::create(['name' => 'School B', 'code' => 'school-b']);
        $admin = $this->makeSchoolAdmin($ownSchool);

        $this->actingAs($admin)->getJson("/api/schools/{$ownSchool->id}")->assertOk();
        $this->actingAs($admin)->getJson("/api/schools/{$otherSchool->id}")->assertForbidden();
    }

    public function test_school_admin_can_update_contact_info_but_not_code_or_active_status(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->putJson("/api/schools/{$school->id}", ['motto' => 'Excellence First'])
            ->assertOk()
            ->assertJsonPath('data.motto', 'Excellence First');

        $this->actingAs($admin)->putJson("/api/schools/{$school->id}", ['code' => 'hijacked'])
            ->assertStatus(422);

        $this->actingAs($admin)->putJson("/api/schools/{$school->id}", ['is_active' => false])
            ->assertStatus(422);

        $this->assertDatabaseHas('schools', ['id' => $school->id, 'code' => 'school-a', 'is_active' => true]);
    }

    public function test_school_admin_cannot_update_another_schools_profile(): void
    {
        $ownSchool = School::create(['name' => 'School A', 'code' => 'school-a']);
        $otherSchool = School::create(['name' => 'School B', 'code' => 'school-b']);
        $admin = $this->makeSchoolAdmin($ownSchool);

        $this->actingAs($admin)->putJson("/api/schools/{$otherSchool->id}", ['motto' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_only_super_admin_can_delete_and_restore_a_school(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($admin)->deleteJson("/api/schools/{$school->id}")->assertForbidden();

        $this->actingAs($superAdmin)->deleteJson("/api/schools/{$school->id}")->assertNoContent();
        $this->assertSoftDeleted('schools', ['id' => $school->id]);

        $this->actingAs($superAdmin)->postJson("/api/schools/{$school->id}/restore")->assertOk();
        $this->assertDatabaseHas('schools', ['id' => $school->id, 'deleted_at' => null]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/schools')->assertUnauthorized();
    }
}
