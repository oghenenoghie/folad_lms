<?php

use App\Http\Controllers\Api\AcademicSessionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassArmController;
use App\Http\Controllers\Api\ClassLevelController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TermController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// 'team' resolves spatie's per-school role/permission context from the
// authenticated user — must run after auth:sanctum, before any policy check.
Route::middleware(['auth:sanctum', 'team'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::apiResource('schools', SchoolController::class);

    Route::apiResource('academic-sessions', AcademicSessionController::class);
    Route::post('/academic-sessions/{academic_session}/activate', [AcademicSessionController::class, 'activate']);

    Route::apiResource('terms', TermController::class);
    Route::post('/terms/{term}/activate', [TermController::class, 'activate']);

    Route::apiResource('class-levels', ClassLevelController::class);
    Route::put('/class-levels/{class_level}/subjects', [SubjectController::class, 'syncForClassLevel']);

    Route::apiResource('class-arms', ClassArmController::class);

    Route::apiResource('subjects', SubjectController::class);

    Route::apiResource('staff', StaffController::class);
    Route::post('/staff/{staff}/account', [StaffController::class, 'createAccount']);

    Route::apiResource('students', StudentController::class);
    Route::post('/students/{student}/account', [StudentController::class, 'createAccount']);

    Route::apiResource('guardians', GuardianController::class);
    Route::post('/guardians/{guardian}/account', [GuardianController::class, 'createAccount']);
    Route::put('/guardians/{guardian}/students', [GuardianController::class, 'syncStudents']);

    Route::apiResource('enrollments', EnrollmentController::class);
});
