<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Enrollment::class);
    }

    public function rules(): array
    {
        // Tenant staff never choose the school — it's implied by their own account.
        // Only super_admin (no school of their own) must name one explicitly.
        $schoolId = Tenancy::schoolId();
        $effectiveSchoolId = $schoolId ?? $this->input('school_id');

        return [
            'school_id' => $schoolId
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id'],

            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
                // One row per student per academic session — matches the
                // enrollments table's unique(['student_id','academic_session_id']).
                // Moving a student to a different class arm within the same
                // session is an update to the existing row, not a new one.
                Rule::unique('enrollments')->where(fn ($q) => $q->where('academic_session_id', $this->input('academic_session_id'))),
            ],

            'class_arm_id' => [
                'required', 'integer',
                Rule::exists('class_arms', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'status' => ['sometimes', Rule::in(['active', 'promoted', 'repeated', 'transferred', 'withdrawn'])],
        ];
    }
}
