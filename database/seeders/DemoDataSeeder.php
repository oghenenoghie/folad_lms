<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\AssessmentComponent;
use App\Models\Attendance;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\GradingScale;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Result;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fills the demo school with enough realistic data to click through every
 * module end to end (classes, staff, students+guardians, results, a day of
 * attendance, published fees with a part-payment, a timetable day, and a
 * couple of announcements). Safe to re-run: everything is looked up by its
 * natural key (admission number, staff number, names, etc.) before creating.
 */
class DemoDataSeeder extends Seeder
{
    protected School $school;

    public function run(): void
    {
        $this->school = School::where('code', 'demo')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);

        [$session, $terms] = $this->seedCalendar();
        $levels = $this->seedClassStructure();
        $subjects = $this->seedSubjects($levels);
        $gradingScale = $this->seedGradingScale();
        $staff = $this->seedStaff($levels);
        $students = $this->seedStudentsAndGuardians($levels);
        $enrollments = $this->seedEnrollments($students, $session);
        $components = $this->seedAssessmentComponents($terms['first']);
        $this->seedResults($enrollments, $subjects['Mathematics'], $components);
        $this->seedAttendance($enrollments, $staff);
        $this->seedFinance($levels, $terms['first'], $enrollments, $staff);
        $this->seedTimetable($levels, $terms['first'], $subjects, $staff);
        $this->seedAnnouncements($levels, $staff);

        $this->command?->info('Demo data seeded: 3 class levels x 2 arms, 12 students + guardians, staff, results, attendance, published fees with a payment, a timetable day, and announcements.');
    }

    /** @return array{0: AcademicSession, 1: array<string, Term>} */
    protected function seedCalendar(): array
    {
        $session = $this->school->academicSessions()->firstOrCreate(
            ['name' => '2025/2026'],
            ['start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'is_current' => true],
        );

        $terms = [
            'first' => [1, 'First Term', '2025-09-01', '2025-12-15', true],
            'second' => [2, 'Second Term', '2026-01-05', '2026-04-02', false],
            'third' => [3, 'Third Term', '2026-04-20', '2026-07-24', false],
        ];

        $created = [];
        foreach ($terms as $key => [$sequence, $name, $start, $end, $isCurrent]) {
            $created[$key] = Term::firstOrCreate(
                ['academic_session_id' => $session->id, 'sequence' => $sequence],
                ['school_id' => $this->school->id, 'name' => $name, 'start_date' => $start, 'end_date' => $end, 'is_current' => $isCurrent],
            );
        }

        return [$session, $created];
    }

    /** @return array<string, ClassLevel> keyed by level name, each with ->arms loaded */
    protected function seedClassStructure(): array
    {
        $levels = [];
        foreach (['JSS 1' => 1, 'JSS 2' => 2, 'SS 1' => 3] as $name => $rank) {
            $level = ClassLevel::firstOrCreate(
                ['school_id' => $this->school->id, 'name' => $name],
                ['rank' => $rank],
            );

            foreach (['A', 'B'] as $armName) {
                ClassArm::firstOrCreate(
                    ['class_level_id' => $level->id, 'name' => $armName],
                    ['school_id' => $this->school->id],
                );
            }

            $levels[$name] = $level->load('arms');
        }

        return $levels;
    }

    /** @return array<string, Subject> */
    protected function seedSubjects(array $levels): array
    {
        $subjects = [];
        foreach (['Mathematics', 'English Language', 'Basic Science', 'Social Studies', 'French'] as $name) {
            $subject = Subject::firstOrCreate(['school_id' => $this->school->id, 'name' => $name], ['is_core' => $name !== 'French']);
            $subject->classLevels()->syncWithoutDetaching(collect($levels)->pluck('id'));
            $subjects[$name] = $subject;
        }

        return $subjects;
    }

    protected function seedGradingScale(): GradingScale
    {
        $scale = GradingScale::firstOrCreate(
            ['school_id' => $this->school->id, 'class_level_id' => null, 'name' => 'School Default'],
            ['is_default' => true],
        );

        if ($scale->bands()->count() === 0) {
            $scale->bands()->createMany([
                ['school_id' => $this->school->id, 'grade' => 'A', 'min_score' => 70, 'max_score' => 100, 'remark' => 'Excellent'],
                ['school_id' => $this->school->id, 'grade' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'remark' => 'Very Good'],
                ['school_id' => $this->school->id, 'grade' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'remark' => 'Good'],
                ['school_id' => $this->school->id, 'grade' => 'D', 'min_score' => 40, 'max_score' => 49.99, 'remark' => 'Pass'],
                ['school_id' => $this->school->id, 'grade' => 'F', 'min_score' => 0, 'max_score' => 39.99, 'remark' => 'Fail'],
            ]);
        }

        return $scale;
    }

    /** @return array{principal: Staff, teachers: array<int, Staff>, accountant: Staff, bursar: Staff} */
    protected function seedStaff(array $levels): array
    {
        $makeStaffUser = function (string $email, string $name, string $role) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['school_id' => $this->school->id, 'name' => $name, 'password' => 'password'],
            );

            if (! $user->hasRole($role)) {
                $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web', 'school_id' => $this->school->id]));
            }

            return $user;
        };

        $makeStaff = function (string $staffNumber, string $first, string $last, ?string $designation, User $user) {
            return Staff::firstOrCreate(
                ['school_id' => $this->school->id, 'staff_number' => $staffNumber],
                [
                    'user_id' => $user->id, 'first_name' => $first, 'last_name' => $last, 'gender' => 'female',
                    'designation' => $designation, 'employment_date' => '2023-09-01',
                ],
            );
        };

        $principal = $makeStaff('STF-001', 'Adaeze', 'Okonkwo', 'Principal',
            $makeStaffUser('principal@demo.folad.test', 'Adaeze Okonkwo', 'principal'));

        $teacherNames = [['Chinedu', 'Balogun'], ['Ngozi', 'Adeyemi'], ['Tunde', 'Bello']];
        $arms = collect($levels)->flatMap(fn ($level) => $level->arms)->values();

        $teachers = [];
        foreach ($teacherNames as $i => [$first, $last]) {
            $user = $makeStaffUser("teacher{$i}@demo.folad.test", "{$first} {$last}", 'teacher');
            $staff = $makeStaff('STF-10'.($i + 1), $first, $last, 'Class Teacher', $user);

            // Make each teacher the form teacher of one class arm.
            $arm = $arms->get($i);
            if ($arm && ! $arm->form_teacher_id) {
                $arm->update(['form_teacher_id' => $staff->id]);
            }

            $teachers[] = $staff;
        }

        $accountant = $makeStaff('STF-201', 'Funmilayo', 'Eze', 'Accountant',
            $makeStaffUser('accountant@demo.folad.test', 'Funmilayo Eze', 'accountant'));
        $bursar = $makeStaff('STF-202', 'Ibrahim', 'Musa', 'Bursar',
            $makeStaffUser('bursar@demo.folad.test', 'Ibrahim Musa', 'bursar'));

        return compact('principal', 'teachers', 'accountant', 'bursar');
    }

    /** @return Collection<int, Student> */
    protected function seedStudentsAndGuardians(array $levels): Collection
    {
        $firstNames = ['Amaka', 'Bayo', 'Chiamaka', 'David', 'Efe', 'Fatima', 'Gbenga', 'Halima', 'Ike', 'Joy', 'Kunle', 'Lola'];
        $genders = ['female', 'male'];

        $students = collect();
        $admissionSeq = 1;

        foreach ($levels as $level) {
            foreach ($level->arms as $arm) {
                foreach (range(1, 2) as $i) {
                    $first = $firstNames[($admissionSeq - 1) % count($firstNames)];
                    $admissionNumber = sprintf('FDS-%04d', $admissionSeq);

                    $student = Student::firstOrCreate(
                        ['school_id' => $this->school->id, 'admission_number' => $admissionNumber],
                        [
                            'first_name' => $first, 'last_name' => 'Student'.$admissionSeq,
                            'gender' => $genders[$admissionSeq % 2], 'date_of_birth' => now()->subYears(10 + $level->rank)->toDateString(),
                            'admission_date' => '2025-09-01',
                        ],
                    );

                    if ($student->guardians()->count() === 0) {
                        $guardianUser = User::firstOrCreate(
                            ['email' => "parent{$admissionSeq}@demo.folad.test"],
                            ['school_id' => $this->school->id, 'name' => "Parent of {$first}", 'password' => 'password'],
                        );
                        if (! $guardianUser->hasRole('guardian')) {
                            $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $this->school->id]));
                        }

                        $guardian = Guardian::firstOrCreate(
                            ['school_id' => $this->school->id, 'user_id' => $guardianUser->id],
                            ['first_name' => 'Parent', 'last_name' => 'Of'.$admissionSeq, 'phone' => '0800000'.str_pad((string) $admissionSeq, 4, '0', STR_PAD_LEFT)],
                        );
                        $guardian->students()->attach($student->id, ['relationship' => 'Parent', 'is_primary_contact' => true]);
                    }

                    $students->push([$student, $arm]);
                    $admissionSeq++;
                }
            }
        }

        return $students;
    }

    /** @return Collection<int, Enrollment> */
    protected function seedEnrollments(Collection $students, AcademicSession $session): Collection
    {
        return $students->map(function ($pair) use ($session) {
            [$student, $arm] = $pair;

            return Enrollment::firstOrCreate(
                ['student_id' => $student->id, 'academic_session_id' => $session->id],
                ['school_id' => $this->school->id, 'class_arm_id' => $arm->id, 'status' => 'active'],
            );
        });
    }

    /** @return array<string, AssessmentComponent> */
    protected function seedAssessmentComponents(Term $firstTerm): array
    {
        $components = [];
        foreach ([['1st CA', 20, 1], ['2nd CA', 20, 2], ['Exam', 60, 3]] as [$name, $max, $sequence]) {
            $components[$name] = AssessmentComponent::firstOrCreate(
                ['term_id' => $firstTerm->id, 'name' => $name],
                ['school_id' => $this->school->id, 'max_score' => $max, 'sequence' => $sequence],
            );
        }

        return $components;
    }

    protected function seedResults(Collection $enrollments, Subject $mathematics, array $components): void
    {
        // Just JSS 1 A's two students get sample scores, enough for the report endpoint to compute something.
        $jss1a = $enrollments->take(2);
        $scores = [['1st CA', 17], ['2nd CA', 18], ['Exam', 55]];

        foreach ($jss1a as $enrollment) {
            foreach ($scores as [$componentName, $score]) {
                Result::firstOrCreate(
                    ['enrollment_id' => $enrollment->id, 'subject_id' => $mathematics->id, 'assessment_component_id' => $components[$componentName]->id],
                    ['school_id' => $this->school->id, 'score' => $score],
                );
            }
        }
    }

    protected function seedAttendance(Collection $enrollments, array $staff): void
    {
        $jss1a = $enrollments->take(2);
        $date = now()->subDay()->toDateString();

        foreach ($jss1a as $i => $enrollment) {
            Attendance::firstOrCreate(
                ['enrollment_id' => $enrollment->id, 'date' => $date],
                ['school_id' => $this->school->id, 'status' => $i === 0 ? 'present' : 'late', 'recorded_by' => $staff['teachers'][0]->id],
            );
        }
    }

    protected function seedFinance(array $levels, Term $firstTerm, Collection $enrollments, array $staff): void
    {
        $jss1 = $levels['JSS 1'];

        $structure = FeeStructure::firstOrCreate(
            ['class_level_id' => $jss1->id, 'term_id' => $firstTerm->id],
            ['school_id' => $this->school->id, 'name' => 'JSS 1 First Term Fees'],
        );

        if ($structure->items()->count() === 0) {
            $structure->items()->createMany([
                ['school_id' => $this->school->id, 'name' => 'Tuition', 'amount' => 5_000_00],
                ['school_id' => $this->school->id, 'name' => 'PTA', 'amount' => 500_00],
                ['school_id' => $this->school->id, 'name' => 'Sports', 'amount' => 200_00],
            ]);
        }

        if (! $structure->isPublished()) {
            $structure->update(['published_at' => now()]);
        }

        $amountDue = (int) $structure->items()->sum('amount');
        $jss1EnrollmentIds = $enrollments->take(2)->pluck('id'); // the two JSS 1 A students

        foreach ($jss1EnrollmentIds as $enrollmentId) {
            $invoice = Invoice::firstOrCreate(
                ['enrollment_id' => $enrollmentId, 'term_id' => $firstTerm->id],
                [
                    'school_id' => $this->school->id, 'fee_structure_id' => $structure->id,
                    'amount_due' => $amountDue, 'issued_at' => now()->toDateString(),
                ],
            );

            if ($invoice->items()->count() === 0) {
                $invoice->items()->createMany($structure->items->map(fn ($item) => [
                    'school_id' => $this->school->id, 'name' => $item->name, 'amount' => $item->amount,
                ])->all());
            }
        }

        // One of the two invoices gets a partial payment, to show a mixed unpaid/partial state.
        $firstInvoice = Invoice::where('enrollment_id', $jss1EnrollmentIds->first())->where('term_id', $firstTerm->id)->first();
        if ($firstInvoice && $firstInvoice->payments()->count() === 0) {
            Payment::create([
                'school_id' => $this->school->id, 'invoice_id' => $firstInvoice->id, 'amount' => 2_000_00,
                'method' => 'bank_transfer', 'reference' => 'DEMO-PAY-1', 'paid_at' => now()->toDateString(),
                'recorded_by' => $staff['accountant']->id,
            ]);
        }
    }

    protected function seedTimetable(array $levels, Term $firstTerm, array $subjects, array $staff): void
    {
        $periods = [
            ['Period 1', '08:00', '08:40', 1, true],
            ['Period 2', '08:40', '09:20', 2, true],
            ['Break', '09:20', '09:40', 3, false],
            ['Period 3', '09:40', '10:20', 4, true],
        ];

        $created = [];
        foreach ($periods as [$name, $start, $end, $sequence, $isTeaching]) {
            $created[$name] = Period::firstOrCreate(
                ['school_id' => $this->school->id, 'sequence' => $sequence],
                ['name' => $name, 'start_time' => $start, 'end_time' => $end, 'is_teaching_period' => $isTeaching],
            );
        }

        $jss1a = $levels['JSS 1']->arms->firstWhere('name', 'A');
        $entries = [
            ['Period 1', $subjects['Mathematics'], $staff['teachers'][0]],
            ['Period 2', $subjects['English Language'], $staff['teachers'][1]],
            ['Period 3', $subjects['Basic Science'], $staff['teachers'][2]],
        ];

        foreach ($entries as [$periodName, $subject, $teacher]) {
            TimetableEntry::firstOrCreate(
                ['class_arm_id' => $jss1a->id, 'term_id' => $firstTerm->id, 'day_of_week' => 'monday', 'period_id' => $created[$periodName]->id],
                ['school_id' => $this->school->id, 'subject_id' => $subject->id, 'staff_id' => $teacher->id],
            );
        }
    }

    protected function seedAnnouncements(array $levels, array $staff): void
    {
        $principalUser = $staff['principal']->user;

        Announcement::firstOrCreate(
            ['school_id' => $this->school->id, 'title' => 'Welcome back for the new session'],
            [
                'body' => 'We are excited to welcome all students and staff back for the 2025/2026 academic session.',
                'audience' => 'all', 'published_at' => now()->subDays(3), 'created_by' => $principalUser->id,
            ],
        );

        Announcement::firstOrCreate(
            ['school_id' => $this->school->id, 'title' => 'JSS 1A PTA meeting'],
            [
                'body' => 'A PTA meeting for JSS 1A parents holds this Saturday at 10am in the school hall.',
                'audience' => 'guardians', 'class_arm_id' => $levels['JSS 1']->arms->firstWhere('name', 'A')->id,
                'published_at' => now()->subDay(), 'created_by' => $principalUser->id,
            ],
        );

        Announcement::firstOrCreate(
            ['school_id' => $this->school->id, 'title' => 'Draft: Third term resumption date'],
            [
                'body' => 'Placeholder -- date to be confirmed by the board.',
                'audience' => 'all', 'published_at' => null, 'created_by' => $principalUser->id,
            ],
        );
    }
}
