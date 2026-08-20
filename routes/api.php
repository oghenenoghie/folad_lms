<?php

use App\Http\Controllers\Api\AcademicSessionController;
use App\Http\Controllers\Api\AssessmentComponentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassArmController;
use App\Http\Controllers\Api\ClassLevelController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\FeeStructureController;
use App\Http\Controllers\Api\GradingScaleController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TermController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'permissions.team'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('/user', fn (Request $request) => [
        'id' => $request->user()->id,
        'name' => $request->user()->name,
        'email' => $request->user()->email,
        'school_id' => $request->user()->school_id,
        'roles' => $request->user()->getRoleNames(),
    ]);

    Route::apiResource('students', StudentController::class);
    Route::post('students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore');

    Route::apiResource('guardians', GuardianController::class);
    Route::post('guardians/{guardian}/restore', [GuardianController::class, 'restore'])->name('guardians.restore');
    Route::post('guardians/{guardian}/students', [GuardianController::class, 'attachStudent'])->name('guardians.students.attach');
    Route::delete('guardians/{guardian}/students/{student}', [GuardianController::class, 'detachStudent'])->name('guardians.students.detach');

    Route::apiResource('staff', StaffController::class);
    Route::post('staff/{staff}/restore', [StaffController::class, 'restore'])->name('staff.restore');

    Route::apiResource('class-levels', ClassLevelController::class);
    Route::apiResource('class-arms', ClassArmController::class);

    Route::apiResource('enrollments', EnrollmentController::class);

    Route::apiResource('schools', SchoolController::class);
    Route::post('schools/{school}/restore', [SchoolController::class, 'restore'])->name('schools.restore');

    Route::apiResource('academic-sessions', AcademicSessionController::class);
    Route::apiResource('terms', TermController::class);

    Route::apiResource('subjects', SubjectController::class);
    Route::put('subjects/{subject}/class-levels', [SubjectController::class, 'syncClassLevels'])->name('subjects.class-levels.sync');

    Route::apiResource('grading-scales', GradingScaleController::class);
    Route::put('grading-scales/{grading_scale}/bands', [GradingScaleController::class, 'syncBands'])->name('grading-scales.bands.sync');

    Route::apiResource('assessment-components', AssessmentComponentController::class);

    // Must come before the apiResource below, or "report" would be swallowed
    // as the {result} id parameter on GET results/{result}.
    Route::get('results/report', [ResultController::class, 'report'])->name('results.report');
    Route::apiResource('results', ResultController::class);

    // Static paths must come before {attendance}, same reasoning as results/report.
    Route::post('attendances/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendances.bulk-mark');
    Route::get('attendances/summary', [AttendanceController::class, 'summary'])->name('attendances.summary');
    Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('attendances/{attendance}', [AttendanceController::class, 'show'])->name('attendances.show');
    Route::put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
    Route::patch('attendances/{attendance}', [AttendanceController::class, 'update']);
    Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');

    Route::apiResource('fee-structures', FeeStructureController::class);
    Route::put('fee-structures/{fee_structure}/items', [FeeStructureController::class, 'syncItems'])->name('fee-structures.items.sync');
    Route::post('fee-structures/{fee_structure}/publish', [FeeStructureController::class, 'publish'])->name('fee-structures.publish');

    // No store route -- invoices are only ever generated via fee-structures/{fee_structure}/publish.
    Route::apiResource('invoices', InvoiceController::class)->except(['store']);

    // No update/destroy routes -- payments are append-only; correct a mistake via reverse.
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show', 'store']);
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
});
