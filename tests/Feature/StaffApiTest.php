<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffApiTest extends TestCase
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

    protected function makeStaff(School $school, array $overrides = []): Staff
    {
        return Staff::create(array_merge([
            'school_id'    => $school->id,
            'staff_number' => 'STF-'.$school->id.'-'.random_int(1000, 9999),
            'first_name'   => 'Tunde',
            'last_name'    => 'Bello',
            'designation'  => 'Mathematics Teacher',
        ], $overrides));
    }

    public function test_school_admin_only_sees_staff_from_their_own_school(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $this->makeStaff($schoolA, ['first_name' => 'Alice']);
        $this->makeStaff($schoolB, ['first_name' => 'Bob']);

        $admin = $this->makeSchoolAdmin($schoolA);

        $response = $this->actingAs($admin)->getJson('/api/staff');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('first_name');
        $this->assertTrue($names->contains('Alice'));
        $this->assertFalse($names->contains('Bob'));
    }

    public function test_school_admin_can_create_a_staff_member(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/staff', [
            'staff_number' => 'STF-100',
            'first_name'   => 'Kemi',
            'last_name'    => 'Adeyemi',
            'designation'  => 'Vice Principal',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.full_name', 'Kemi Adeyemi');
        $this->assertDatabaseHas('staff', [
            'staff_number' => 'STF-100',
            'school_id'    => $school->id,
        ]);
    }

    public function test_staff_number_must_be_unique_within_a_school_but_not_across_schools(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $this->makeStaff($schoolA, ['staff_number' => 'STF-1']);
        $adminA = $this->makeSchoolAdmin($schoolA);
        $adminB = $this->makeSchoolAdmin($schoolB);

        $this->actingAs($adminA)->postJson('/api/staff', [
            'staff_number' => 'STF-1',
            'first_name'   => 'Second',
            'last_name'    => 'Person',
        ])->assertStatus(422);

        $this->actingAs($adminB)->postJson('/api/staff', [
            'staff_number' => 'STF-1',
            'first_name'   => 'Third',
            'last_name'    => 'Person',
        ])->assertCreated();
    }

    public function test_a_staff_member_from_another_school_is_not_visible_or_editable(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $foreignStaff = $this->makeStaff($schoolB);
        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/staff/{$foreignStaff->id}")->assertNotFound();
        $this->actingAs($adminA)->putJson("/api/staff/{$foreignStaff->id}", ['phone' => '08000000000'])
            ->assertNotFound();
    }

    public function test_teacher_cannot_list_staff_but_can_view_their_own_record(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $teacherUser = $this->makeTeacherUser($school);
        $ownStaff = $this->makeStaff($school, ['user_id' => $teacherUser->id, 'first_name' => 'Own']);
        $otherStaff = $this->makeStaff($school, ['first_name' => 'Other']);

        $this->actingAs($teacherUser)->getJson('/api/staff')->assertForbidden();
        $this->actingAs($teacherUser)->getJson("/api/staff/{$ownStaff->id}")->assertOk();
        $this->actingAs($teacherUser)->getJson("/api/staff/{$otherStaff->id}")->assertForbidden();
    }

    public function test_staff_member_can_update_their_own_contact_info_but_not_hr_fields(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $teacherUser = $this->makeTeacherUser($school);
        $ownStaff = $this->makeStaff($school, ['user_id' => $teacherUser->id]);

        $this->actingAs($teacherUser)->putJson("/api/staff/{$ownStaff->id}", ['phone' => '08099990000'])
            ->assertOk()
            ->assertJsonPath('data.phone', '08099990000');

        $this->actingAs($teacherUser)->putJson("/api/staff/{$ownStaff->id}", ['designation' => 'Principal'])
            ->assertStatus(422);

        $this->assertDatabaseHas('staff', ['id' => $ownStaff->id, 'designation' => 'Mathematics Teacher']);
    }

    public function test_teacher_cannot_create_staff(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $teacherUser = $this->makeTeacherUser($school);

        $this->actingAs($teacherUser)->postJson('/api/staff', [
            'staff_number' => 'STF-200',
            'first_name'   => 'New',
            'last_name'    => 'Staff',
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/staff')->assertUnauthorized();
    }

    public function test_school_admin_can_soft_delete_and_restore_a_staff_member(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $staff = $this->makeStaff($school);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->deleteJson("/api/staff/{$staff->id}")->assertNoContent();
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);

        $this->actingAs($admin)->postJson("/api/staff/{$staff->id}/restore")->assertOk();
        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'deleted_at' => null]);
    }
}
