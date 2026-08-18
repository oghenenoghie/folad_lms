<?php

use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permissions.team'])->group(function () {
    Route::get('/user', fn () => request()->user());

    Route::apiResource('students', StudentController::class);
    Route::post('students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore');

    Route::apiResource('guardians', GuardianController::class);
    Route::post('guardians/{guardian}/restore', [GuardianController::class, 'restore'])->name('guardians.restore');
    Route::post('guardians/{guardian}/students', [GuardianController::class, 'attachStudent'])->name('guardians.students.attach');
    Route::delete('guardians/{guardian}/students/{student}', [GuardianController::class, 'detachStudent'])->name('guardians.students.detach');

    Route::apiResource('staff', StaffController::class);
    Route::post('staff/{staff}/restore', [StaffController::class, 'restore'])->name('staff.restore');
});
