<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('enrollment'));
    }

    public function rules(): array
    {
        $enrollment = $this->route('enrollment');
        $schoolId = Tenancy::schoolId() ?? $enrollment->school_id;

        return [
            // student_id and academic_session_id are deliberately rejected here
            // — that pair is the enrollment's identity (see the unique
            // constraint). Re-pointing either is really "delete and re-create",
            // not a field edit. Moving class arms within the same session is
            // the one thing this endpoint is for.
            'student_id'           => ['prohibited'],
            'academic_session_id'  => ['prohibited'],

            'class_arm_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('class_arms', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],

            'status' => ['sometimes', Rule::in(['active', 'promoted', 'repeated', 'transferred', 'withdrawn'])],
        ];
    }
}
