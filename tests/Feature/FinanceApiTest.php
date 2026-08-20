<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Guardian as GuardianModel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicSession $session;

    protected Term $term;

    protected ClassLevel $level;

    protected ClassArm $arm;

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

    protected function makeDraftFeeStructure(): FeeStructure
    {
        return FeeStructure::create([
            'school_id' => $this->school->id, 'class_level_id' => $this->level->id, 'term_id' => $this->term->id,
            'name' => 'JSS 1 First Term Fees',
        ]);
    }

    public function test_accountant_can_create_a_fee_structure_and_teacher_cannot(): void
    {
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/fee-structures', [
            'class_level_id' => $this->level->id, 'term_id' => $this->term->id, 'name' => 'JSS 1 First Term Fees',
        ])->assertCreated()->assertJsonPath('data.is_published', false);

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->postJson('/api/fee-structures', [
            'class_level_id' => $this->level->id, 'term_id' => $this->term->id, 'name' => 'Duplicate attempt',
        ])->assertForbidden();
    }

    public function test_can_sync_items_on_a_draft_structure_but_not_after_publishing(): void
    {
        $bursar = $this->makeUserWithRole('bursar');
        $structure = $this->makeDraftFeeStructure();

        $this->actingAs($bursar)->putJson("/api/fee-structures/{$structure->id}/items", [
            'items' => [
                ['name' => 'Tuition', 'amount' => 500000],
                ['name' => 'PTA', 'amount' => 50000],
            ],
        ])->assertOk()->assertJsonPath('data.total_amount', 550000);

        $this->actingAs($bursar)->postJson("/api/fee-structures/{$structure->id}/publish")->assertOk();

        $this->actingAs($bursar)->putJson("/api/fee-structures/{$structure->id}/items", [
            'items' => [['name' => 'Tuition', 'amount' => 600000]],
        ])->assertStatus(409);
    }

    public function test_cannot_publish_a_fee_structure_with_no_items(): void
    {
        $bursar = $this->makeUserWithRole('bursar');
        $structure = $this->makeDraftFeeStructure();

        $this->actingAs($bursar)->postJson("/api/fee-structures/{$structure->id}/publish")->assertStatus(422);
    }

    public function test_cannot_change_class_level_or_term_on_update(): void
    {
        $bursar = $this->makeUserWithRole('bursar');
        $structure = $this->makeDraftFeeStructure();
        $otherLevel = ClassLevel::create(['school_id' => $this->school->id, 'name' => 'JSS 2', 'rank' => 2]);

        $this->actingAs($bursar)->putJson("/api/fee-structures/{$structure->id}", [
            'class_level_id' => $otherLevel->id,
        ])->assertStatus(422);
    }

    public function test_publishing_generates_invoices_for_active_enrollments_and_backfills_new_ones_on_republish(): void
    {
        $bursar = $this->makeUserWithRole('bursar');
        $e1 = $this->makeEnrollment('ADM-1');
        $e2 = $this->makeEnrollment('ADM-2');

        $structure = $this->makeDraftFeeStructure();
        $structure->items()->create(['school_id' => $this->school->id, 'name' => 'Tuition', 'amount' => 500000]);

        $response = $this->actingAs($bursar)->postJson("/api/fee-structures/{$structure->id}/publish");
        $response->assertOk()->assertJsonPath('data.invoices_generated', 2);

        $this->assertDatabaseHas('invoices', ['enrollment_id' => $e1->id, 'term_id' => $this->term->id, 'amount_due' => 500000]);
        $this->assertDatabaseHas('invoices', ['enrollment_id' => $e2->id, 'term_id' => $this->term->id, 'amount_due' => 500000]);
        $this->assertDatabaseCount('invoice_items', 2);

        // A student enrolling after the first publish should be picked up
        // by a second call without duplicating the two that already exist.
        $e3 = $this->makeEnrollment('ADM-3');
        $response = $this->actingAs($bursar)->postJson("/api/fee-structures/{$structure->id}/publish");
        $response->assertOk()->assertJsonPath('data.invoices_generated', 1);
        $this->assertDatabaseHas('invoices', ['enrollment_id' => $e3->id, 'term_id' => $this->term->id]);
        $this->assertDatabaseCount('invoices', 3);
    }

    public function test_deleting_a_fee_structure_with_generated_invoices_is_blocked(): void
    {
        $bursar = $this->makeUserWithRole('bursar');
        $this->makeEnrollment('ADM-1');
        $structure = $this->makeDraftFeeStructure();
        $structure->items()->create(['school_id' => $this->school->id, 'name' => 'Tuition', 'amount' => 500000]);
        $this->actingAs($bursar)->postJson("/api/fee-structures/{$structure->id}/publish")->assertOk();

        $this->actingAs($bursar)->deleteJson("/api/fee-structures/{$structure->id}")->assertStatus(409);
    }

    protected ?FeeStructure $publishedStructure = null;

    protected function publishedInvoiceFor(Enrollment $enrollment, int $amount = 500000): Invoice
    {
        $structure = $this->publishedStructure ??= FeeStructure::create([
            'school_id' => $this->school->id, 'class_level_id' => $this->level->id, 'term_id' => $this->term->id,
            'name' => 'JSS 1 First Term Fees', 'published_at' => now(),
        ]);
        if ($structure->items()->count() === 0) {
            $structure->items()->create(['school_id' => $this->school->id, 'name' => 'Tuition', 'amount' => $amount]);
        }

        $invoice = Invoice::create([
            'school_id' => $this->school->id, 'enrollment_id' => $enrollment->id, 'fee_structure_id' => $structure->id,
            'term_id' => $this->term->id, 'amount_due' => $amount, 'issued_at' => now()->toDateString(),
        ]);
        $invoice->items()->create(['school_id' => $this->school->id, 'name' => 'Tuition', 'amount' => $amount]);

        return $invoice;
    }

    public function test_recording_a_payment_updates_the_invoice_balance_and_status(): void
    {
        $accountant = $this->makeUserWithRole('accountant');
        $enrollment = $this->makeEnrollment('ADM-1');
        $invoice = $this->publishedInvoiceFor($enrollment, 500000);

        $this->actingAs($accountant)->postJson('/api/payments', [
            'invoice_id' => $invoice->id, 'amount' => 200000, 'method' => 'cash',
        ])->assertCreated();

        $this->actingAs($accountant)->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.amount_paid', 200000)
            ->assertJsonPath('data.balance', 300000)
            ->assertJsonPath('data.status', 'partial');

        $this->actingAs($accountant)->postJson('/api/payments', [
            'invoice_id' => $invoice->id, 'amount' => 300000, 'method' => 'bank_transfer', 'reference' => 'TXN-1',
        ])->assertCreated();

        $this->actingAs($accountant)->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_reversing_a_payment_nets_out_the_balance_and_cannot_be_reversed_twice(): void
    {
        $accountant = $this->makeUserWithRole('accountant');
        $enrollment = $this->makeEnrollment('ADM-1');
        $invoice = $this->publishedInvoiceFor($enrollment, 500000);

        $payment = Payment::create([
            'school_id' => $this->school->id, 'invoice_id' => $invoice->id, 'amount' => 200000,
            'method' => 'cash', 'paid_at' => now()->toDateString(),
        ]);

        $this->actingAs($accountant)->postJson("/api/payments/{$payment->id}/reverse")
            ->assertCreated()
            ->assertJsonPath('data.amount', -200000)
            ->assertJsonPath('data.is_reversal', true);

        $this->actingAs($accountant)->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.amount_paid', 0)
            ->assertJsonPath('data.status', 'unpaid');

        // Can't reverse the same payment twice.
        $this->actingAs($accountant)->postJson("/api/payments/{$payment->id}/reverse")->assertStatus(422);
    }

    public function test_cannot_reverse_a_reversal(): void
    {
        $accountant = $this->makeUserWithRole('accountant');
        $enrollment = $this->makeEnrollment('ADM-1');
        $invoice = $this->publishedInvoiceFor($enrollment, 500000);

        $payment = Payment::create([
            'school_id' => $this->school->id, 'invoice_id' => $invoice->id, 'amount' => 200000,
            'method' => 'cash', 'paid_at' => now()->toDateString(),
        ]);
        $reversal = $this->actingAs($accountant)->postJson("/api/payments/{$payment->id}/reverse")->json('data');

        $this->actingAs($accountant)->postJson("/api/payments/{$reversal['id']}/reverse")->assertStatus(422);
    }

    public function test_invoice_identity_and_amount_fields_are_locked_on_update(): void
    {
        $accountant = $this->makeUserWithRole('accountant');
        $enrollment = $this->makeEnrollment('ADM-1');
        $invoice = $this->publishedInvoiceFor($enrollment, 500000);

        $this->actingAs($accountant)->putJson("/api/invoices/{$invoice->id}", [
            'amount_due' => 999999,
        ])->assertStatus(422);

        $this->actingAs($accountant)->putJson("/api/invoices/{$invoice->id}", [
            'due_date' => '2025-12-01',
        ])->assertOk()->assertJsonPath('data.due_date', '2025-12-01');
    }

    public function test_guardian_can_view_their_own_linked_students_invoice_but_not_another(): void
    {
        $e1 = $this->makeEnrollment('ADM-1');
        $e2 = $this->makeEnrollment('ADM-2');
        $invoice1 = $this->publishedInvoiceFor($e1);
        $invoice2 = $this->publishedInvoiceFor($e2);

        $guardianUser = User::create([
            'school_id' => $this->school->id, 'name' => 'Parent', 'email' => 'parent@example.com', 'password' => 'secret',
        ]);
        $guardian = GuardianModel::create([
            'school_id' => $this->school->id, 'user_id' => $guardianUser->id, 'first_name' => 'Parent', 'last_name' => 'One', 'phone' => '08000000000',
        ]);
        $guardian->students()->attach($e1->student_id, ['relationship' => 'Mother', 'is_primary_contact' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $guardianUser->assignRole(Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web', 'school_id' => $this->school->id]));

        $this->actingAs($guardianUser)->getJson("/api/invoices/{$invoice1->id}")->assertOk();
        $this->actingAs($guardianUser)->getJson("/api/invoices/{$invoice2->id}")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/fee-structures')->assertStatus(401);
        $this->getJson('/api/invoices')->assertStatus(401);
        $this->getJson('/api/payments')->assertStatus(401);
    }
}
