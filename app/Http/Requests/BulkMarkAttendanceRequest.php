<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Models\ClassArm;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classArm = ClassArm::find($this->input('class_arm_id'));

        // A missing/invalid class_arm_id shouldn't 403 -- let the "exists"
        // rule below surface it as a proper 422 instead.
        if (! $classArm) {
            return true;
        }

        return $this->user()->can('markForClassArm', [Attendance::class, $classArm]);
    }

    public function rules(): array
    {
        // Tenant staff never choose the school — it's implied by their own account.
        // Only super_admin (no school of their own) must name one explicitly.
        $schoolId = Tenancy::schoolId();
        $effectiveSchoolId = $schoolId ?? $this->input('school_id');
        $classArmId = $this->input('class_arm_id');

        return [
            'school_id' => $schoolId
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id'],

            'class_arm_id' => [
                'required', 'integer',
                Rule::exists('class_arms', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'date' => ['required', 'date', 'before_or_equal:today'],

            'records'                  => ['required', 'array', 'min:1'],
            'records.*.enrollment_id'  => [
                'required', 'integer', 'distinct',
                Rule::exists('enrollments', 'id')->where(fn ($q) => $q
                    ->where('school_id', $effectiveSchoolId)
                    ->where('class_arm_id', $classArmId)),
            ],
            'records.*.status'  => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'records.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
