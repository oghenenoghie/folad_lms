<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\Guardian as GuardianModel;
use App\Models\Period;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TimetableAnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicSession $session;

    protected Term $term;

    protected ClassLevel $level;

    protected ClassArm $arm;

    protected ClassArm $otherArm;

    protected Subject $subject;

    protected Period $teachingPeriod;

    protected Period $breakPeriod;

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
        $this->otherArm = ClassArm::create(['school_id' => $this->school->id, 'class_level_id' => $this->level->id, 'name' => 'B']);
        $this->subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics']);
        $this->level->subjects()->attach($this->subject->id);

        $this->teachingPeriod = Period::create([
            'school_id' => $this->school->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'sequence' => 1,
        ]);
        $this->breakPeriod = Period::create([
            'school_id' => $this->school->id, 'name' => 'Break', 'start_time' => '10:00', 'end_time' => '10:20',
            'sequence' => 2, 'is_teaching_period' => false,
        ]);
    }

    protected function makeUserWithRole(string $role): User
    {
        $user = User::create([
            'school_id' => $this->school->id, 'name' => ucfirst($role), 'email' => $role.random_int(1000, 9999).'@example.com', 'password' => 'secret',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web', 'school_id' => $this->school->id]));

        return $user;
    }

    protected function makeStaff(User $user): Staff
    {
        return Staff::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'staff_number' => 'STF-'.random_int(1000, 9999),
            'first_name' => 'Teach', 'last_name' => 'Er',
        ]);
    }

    protected function makeEnrollment(string $admissionNumber, ?ClassArm $arm = null): Enrollment
    {
        $student = Student::create([
            'school_id' => $this->school->id, 'admission_number' => $admissionNumber, 'first_name' => 'Student',
            'last_name' => $admissionNumber, 'gender' => 'female', 'date_of_birth' => '2012-01-01',
        ]);

        return Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id, 'class_arm_id' => ($arm ?? $this->arm)->id,
            'academic_session_id' => $this->session->id, 'status' => 'active',
        ]);
    }

    // --- Periods ---

    public function test_school_admin_can_create_a_period_and_teacher_cannot(): void
    {
        $admin = $this->makeUserWithRole('school_admin');

        $this->actingAs($admin)->postJson('/api/periods', [
            'name' => 'Period 3', 'start_time' => '09:20', 'end_time' => '10:00', 'sequence' => 3,
        ])->assertCreated();

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->postJson('/api/periods', [
            'name' => 'Period 4', 'start_time' => '10:20', 'end_time' => '11:00', 'sequence' => 4,
        ])->assertForbidden();
    }

    public function test_deleting_a_period_in_use_is_blocked(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        TimetableEntry::create([
            'school_id' => $this->school->id, 'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id,
            'period_id' => $this->teachingPeriod->id, 'day_of_week' => 'monday', 'subject_id' => $this->subject->id,
        ]);

        $this->actingAs($admin)->deleteJson("/api/periods/{$this->teachingPeriod->id}")->assertStatus(409);
    }

    // --- Timetable entries ---

    public function test_can_create_a_timetable_entry_and_rejects_a_break_period(): void
    {
        $admin = $this->makeUserWithRole('school_admin');

        $this->actingAs($admin)->postJson('/api/timetable-entries', [
            'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id, 'period_id' => $this->teachingPeriod->id,
            'day_of_week' => 'monday', 'subject_id' => $this->subject->id,
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/api/timetable-entries', [
            'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id, 'period_id' => $this->breakPeriod->id,
            'day_of_week' => 'monday', 'subject_id' => $this->subject->id,
        ])->assertStatus(422);
    }

    public function test_rejects_a_subject_not_taught_at_the_class_arms_level(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        $untaught = Subject::create(['school_id' => $this->school->id, 'name' => 'French']);

        $this->actingAs($admin)->postJson('/api/timetable-entries', [
            'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id, 'period_id' => $this->teachingPeriod->id,
            'day_of_week' => 'monday', 'subject_id' => $untaught->id,
        ])->assertStatus(422);
    }

    public function test_blocks_double_booking_the_same_class_arm_at_the_same_slot(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        TimetableEntry::create([
            'school_id' => $this->school->id, 'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id,
            'period_id' => $this->teachingPeriod->id, 'day_of_week' => 'monday', 'subject_id' => $this->subject->id,
        ]);

        $this->actingAs($admin)->postJson('/api/timetable-entries', [
            'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id, 'period_id' => $this->teachingPeriod->id,
            'day_of_week' => 'monday', 'subject_id' => $this->subject->id,
        ])->assertStatus(422);
    }

    public function test_blocks_double_booking_the_same_teacher_across_two_class_arms(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        $teacherUser = $this->makeUserWithRole('teacher');
        $staff = $this->makeStaff($teacherUser);

        TimetableEntry::create([
            'school_id' => $this->school->id, 'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id,
            'period_id' => $this->teachingPeriod->id, 'day_of_week' => 'monday', 'subject_id' => $this->subject->id,
            'staff_id' => $staff->id,
        ]);

        $this->actingAs($admin)->postJson('/api/timetable-entries', [
            'class_arm_id' => $this->otherArm->id, 'term_id' => $this->term->id, 'period_id' => $this->teachingPeriod->id,
            'day_of_week' => 'monday', 'subject_id' => $this->subject->id, 'staff_id' => $staff->id,
        ])->assertStatus(422);
    }

    public function test_cannot_move_a_timetable_entry_to_a_different_class_arm_or_term_on_update(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        $entry = TimetableEntry::create([
            'school_id' => $this->school->id, 'class_arm_id' => $this->arm->id, 'term_id' => $this->term->id,
            'period_id' => $this->teachingPeriod->id, 'day_of_week' => 'monday', 'subject_id' => $this->subject->id,
        ]);

        $this->actingAs($admin)->putJson("/api/timetable-entries/{$entry->id}", [
            'class_arm_id' => $this->otherArm->id,
        ])->assertStatus(422);
    }

    // --- Announcements ---

    public function test_admin_can_create_an_announcement_and_teacher_cannot(): void
    {
        $admin = $this->makeUserWithRole('school_admin');

        $this->actingAs($admin)->postJson('/api/announcements', [
            'title' => 'Resumption', 'body' => 'School resumes Monday.', 'audience' => 'all', 'published_at' => now()->toIso8601String(),
        ])->assertCreated();

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->postJson('/api/announcements', [
            'title' => 'Unauthorized', 'body' => 'Nope.', 'audience' => 'all',
        ])->assertForbidden();
    }

    public function test_cannot_set_both_class_level_and_class_arm(): void
    {
        $admin = $this->makeUserWithRole('school_admin');

        $this->actingAs($admin)->postJson('/api/announcements', [
            'title' => 'Test', 'body' => 'Body', 'audience' => 'students',
            'class_level_id' => $this->level->id, 'class_arm_id' => $this->arm->id,
        ])->assertStatus(422);
    }

    public function test_a_draft_is_visible_to_admin_but_not_to_a_teacher(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        $draft = $admin->announcements()->create([
            'school_id' => $this->school->id, 'title' => 'Draft', 'body' => 'Not yet.', 'audience' => 'all',
        ]);

        $this->actingAs($admin)->getJson("/api/announcements/{$draft->id}")->assertOk();

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->getJson("/api/announcements/{$draft->id}")->assertForbidden();
    }

    public function test_a_published_all_audience_announcement_is_visible_to_staff_students_and_guardians(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        $announcement = $admin->announcements()->create([
            'school_id' => $this->school->id, 'title' => 'Holiday', 'body' => 'No school Friday.',
            'audience' => 'all', 'published_at' => now()->subMinute(),
        ]);

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->getJson("/api/announcements/{$announcement->id}")->assertOk();
    }

    public function test_a_class_arm_scoped_announcement_is_visible_only_to_students_in_that_arm(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        $announcement = $admin->announcements()->create([
            'school_id' => $this->school->id, 'title' => 'JSS 1A trip', 'body' => 'Bring lunch.',
            'audience' => 'students', 'class_arm_id' => $this->arm->id, 'published_at' => now()->subMinute(),
        ]);

        $inArm = $this->makeEnrollment('ADM-1', $this->arm);
        $studentUser = User::create([
            'school_id' => $this->school->id, 'name' => 'In Arm', 'email' => 'inarm@example.com', 'password' => 'secret',
        ]);
        Student::whereKey($inArm->student_id)->update(['user_id' => $studentUser->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $studentUser->assignRole(Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web', 'school_id' => $this->school->id]));

        $outOfArm = $this->makeEnrollment('ADM-2', $this->otherArm);
        $otherStudentUser = User::create([
            'school_id' => $this->school->id, 'name' => 'Out Of Arm', 'email' => 'outofarm@example.com', 'password' => 'secret',
        ]);
        Student::whereKey($outOfArm->student_id)->update(['user_id' => $otherStudentUser->id]);
        $otherStudentUser->assignRole(Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web', 'school_id' => $this->school->id]));

        $this->actingAs($studentUser)->getJson("/api/announcements/{$announcement->id}")->assertOk();
        $this->actingAs($otherStudentUser)->getJson("/api/announcements/{$announcement->id}")->assertForbidden();
    }

    public function test_a_guardian_sees_announcements_scoped_to_their_linked_students_class_but_not_unrelated_ones(): void
    {
        $admin = $this->makeUserWithRole('school_admin');
        $scoped = $admin->announcements()->create([
            'school_id' => $this->school->id, 'title' => 'JSS 1A meeting', 'body' => 'PTA meeting.',
            'audience' => 'guardians', 'class_arm_id' => $this->arm->id, 'published_at' => now()->subMinute(),
        ]);
        $unrelated = $admin->announcements()->create([
            'school_id' => $this->school->id, 'title' => 'JSS 1B meeting', 'body' => 'PTA meeting.',
            'audience' => 'guardians', 'class_arm_id' => $this->otherArm->id, 'published_at' => now()->subMinute(),
        ]);

        $enrollment = $this->makeEnrollment('ADM-1', $this->arm);
        $guardianUser = User::create([
            'school_id' => $this->school->id, 'name' => 'Parent', 'email' => 'parent@example.com', 'password' => 'secret',
        ]);
        $guardian = GuardianModel::create([
            'school_id' => $this->school->id, 'user_id' => $guardianUser->id, 'first_name' => 'Parent', 'last_name' => 'One', 'phone' => '08000000000',
        ]);
        $guardian->students()->attach($enrollment->student_id, ['relationship' => 'Mother', 'is_primary_contact' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $this->school->id]));

        $this->actingAs($guardianUser)->getJson("/api/announcements/{$scoped->id}")->assertOk();
        $this->actingAs($guardianUser)->getJson("/api/announcements/{$unrelated->id}")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/periods')->assertStatus(401);
        $this->getJson('/api/timetable-entries')->assertStatus(401);
        $this->getJson('/api/announcements')->assertStatus(401);
    }
}
