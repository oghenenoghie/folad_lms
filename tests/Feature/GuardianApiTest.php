<?php

namespace Tests\Feature;

use App\Models\Guardian as GuardianModel;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GuardianApiTest extends TestCase
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

    protected function makeGuardian(School $school, array $overrides = []): GuardianModel
    {
        return GuardianModel::create(array_merge([
            'school_id'  => $school->id,
            'first_name' => 'Chika',
            'last_name'  => 'Nwosu',
            'phone'      => '0800'.random_int(1000000, 9999999),
        ], $overrides));
    }

    public function test_school_admin_only_sees_guardians_from_their_own_school(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $this->makeGuardian($schoolA, ['first_name' => 'Alice']);
        $this->makeGuardian($schoolB, ['first_name' => 'Bob']);

        $admin = $this->makeSchoolAdmin($schoolA);

        $response = $this->actingAs($admin)->getJson('/api/guardians');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('first_name');
        $this->assertTrue($names->contains('Alice'));
        $this->assertFalse($names->contains('Bob'));
    }

    public function test_school_admin_can_create_a_guardian(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson('/api/guardians', [
            'first_name' => 'Ngozi',
            'last_name'  => 'Eze',
            'phone'      => '08011112222',
            'email'      => 'ngozi@example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.full_name', 'Ngozi Eze');
        $this->assertDatabaseHas('guardians', [
            'phone'     => '08011112222',
            'school_id' => $school->id,
        ]);
    }

    public function test_a_guardian_from_another_school_is_not_visible_or_editable(): void
    {
        $schoolA = School::create(['name' => 'School A', 'code' => 'school-a']);
        $schoolB = School::create(['name' => 'School B', 'code' => 'school-b']);

        $foreignGuardian = $this->makeGuardian($schoolB);
        $adminA = $this->makeSchoolAdmin($schoolA);

        $this->actingAs($adminA)->getJson("/api/guardians/{$foreignGuardian->id}")->assertNotFound();
        $this->actingAs($adminA)->putJson("/api/guardians/{$foreignGuardian->id}", ['first_name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_school_admin_can_attach_a_student_with_a_relationship(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $guardian = $this->makeGuardian($school);
        $student = $this->makeStudent($school);
        $admin = $this->makeSchoolAdmin($school);

        $response = $this->actingAs($admin)->postJson("/api/guardians/{$guardian->id}/students", [
            'student_id'         => $student->id,
            'relationship'       => 'Mother',
            'is_primary_contact' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('guardian_student', [
            'guardian_id'         => $guardian->id,
            'student_id'          => $student->id,
            'relationship'        => 'Mother',
            'is_primary_contact'  => true,
        ]);
    }

    public function test_attaching_a_second_primary_contact_unsets_the_previous_one(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $student = $this->makeStudent($school);
        $mother = $this->makeGuardian($school, ['first_name' => 'Mother']);
        $father = $this->makeGuardian($school, ['first_name' => 'Father']);
        $admin = $this->makeSchoolAdmin($school);

        $mother->students()->attach($student->id, ['relationship' => 'Mother', 'is_primary_contact' => true]);

        $this->actingAs($admin)->postJson("/api/guardians/{$father->id}/students", [
            'student_id'         => $student->id,
            'relationship'       => 'Father',
            'is_primary_contact' => true,
        ])->assertOk();

        $this->assertDatabaseHas('guardian_student', [
            'guardian_id'        => $father->id,
            'student_id'         => $student->id,
            'is_primary_contact' => true,
        ]);
        $this->assertDatabaseHas('guardian_student', [
            'guardian_id'        => $mother->id,
            'student_id'         => $student->id,
            'is_primary_contact' => false,
        ]);
    }

    public function test_school_admin_can_detach_a_student(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $guardian = $this->makeGuardian($school);
        $student = $this->makeStudent($school);
        $admin = $this->makeSchoolAdmin($school);

        $guardian->students()->attach($student->id, ['relationship' => 'Uncle', 'is_primary_contact' => false]);

        $this->actingAs($admin)->deleteJson("/api/guardians/{$guardian->id}/students/{$student->id}")->assertOk();

        $this->assertDatabaseMissing('guardian_student', [
            'guardian_id' => $guardian->id,
            'student_id'  => $student->id,
        ]);
    }

    public function test_teacher_can_view_but_not_create_guardians(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $guardian = $this->makeGuardian($school);

        $teacher = User::create([
            'school_id' => $school->id,
            'name'      => 'Teacher',
            'email'     => 'teacher@example.com',
            'password'  => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $teacher->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web', 'school_id' => $school->id]));

        $this->actingAs($teacher)->getJson("/api/guardians/{$guardian->id}")->assertOk();

        $this->actingAs($teacher)->postJson('/api/guardians', [
            'first_name' => 'New',
            'last_name'  => 'Guardian',
            'phone'      => '08099998888',
        ])->assertForbidden();
    }

    public function test_guardian_can_only_view_their_own_record(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $ownGuardian = $this->makeGuardian($school, ['first_name' => 'Own']);
        $otherGuardian = $this->makeGuardian($school, ['first_name' => 'Other']);

        $guardianUser = User::create([
            'school_id' => $school->id,
            'name'      => 'Parent',
            'email'     => 'parent@example.com',
            'password'  => 'secret',
        ]);
        $ownGuardian->update(['user_id' => $guardianUser->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $school->id]));

        $this->actingAs($guardianUser)->getJson("/api/guardians/{$ownGuardian->id}")->assertOk();
        $this->actingAs($guardianUser)->getJson("/api/guardians/{$otherGuardian->id}")->assertForbidden();
    }

    public function test_guardian_can_update_their_own_contact_info_but_not_others(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $ownGuardian = $this->makeGuardian($school);
        $otherGuardian = $this->makeGuardian($school);

        $guardianUser = User::create([
            'school_id' => $school->id,
            'name'      => 'Parent',
            'email'     => 'parent@example.com',
            'password'  => 'secret',
        ]);
        $ownGuardian->update(['user_id' => $guardianUser->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $school->id]));

        $this->actingAs($guardianUser)->putJson("/api/guardians/{$ownGuardian->id}", ['phone' => '08012340000'])
            ->assertOk()
            ->assertJsonPath('data.phone', '08012340000');

        $this->actingAs($guardianUser)->putJson("/api/guardians/{$otherGuardian->id}", ['phone' => '08012340000'])
            ->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/guardians')->assertUnauthorized();
    }

    public function test_school_admin_can_soft_delete_and_restore_a_guardian(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $guardian = $this->makeGuardian($school);
        $admin = $this->makeSchoolAdmin($school);

        $this->actingAs($admin)->deleteJson("/api/guardians/{$guardian->id}")->assertNoContent();
        $this->assertSoftDeleted('guardians', ['id' => $guardian->id]);

        $this->actingAs($admin)->postJson("/api/guardians/{$guardian->id}/restore")->assertOk();
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'deleted_at' => null]);
    }
}
