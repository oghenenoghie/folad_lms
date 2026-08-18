<?php

use App\Http\Controllers\Api\AcademicSessionController;
use App\Http\Controllers\Api\AssessmentComponentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassArmController;
use App\Http\Controllers\Api\ClassLevelController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GradingScaleController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TermController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'permissions.team'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('/user', fn (\Illuminate\Http\Request $request) => [
        'id'        => $request->user()->id,
        'name'      => $request->user()->name,
        'email'     => $request->user()->email,
        'school_id' => $request->user()->school_id,
        'roles'     => $request->user()->getRoleNames(),
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
});
