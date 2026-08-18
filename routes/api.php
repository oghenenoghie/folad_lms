<?php

use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permissions.team'])->group(function () {
    Route::get('/user', fn () => request()->user());

    Route::apiResource('students', StudentController::class);
    Route::post('students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore');
});
