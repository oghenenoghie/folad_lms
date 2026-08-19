<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by EnrollmentPolicy via authorizeResource
    }

    public function rules(): array
    {
        $enrollmentId = $this->route('enrollment')?->id;
        $schoolId = $this->user()->school_id;

        return [
            'student_id' => [
                'required',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'class_arm_id' => [
                'required',
                Rule::exists('class_arms', 'id')->where('school_id', $schoolId),
            ],
            'academic_session_id' => [
                'required',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
                Rule::unique('enrollments')
                    ->where('student_id', $this->input('student_id'))
                    ->ignore($enrollmentId),
            ],
            'status' => ['sometimes', Rule::in(['active', 'promoted', 'repeated', 'transferred', 'withdrawn'])],
        ];
    }
}
