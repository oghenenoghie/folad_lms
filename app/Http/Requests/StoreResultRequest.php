<?php

namespace App\Http\Requests;

use App\Models\AssessmentComponent;
use App\Models\Result;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Result::class);
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

            'enrollment_id' => [
                'required', 'integer',
                Rule::exists('enrollments', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'assessment_component_id' => [
                'required', 'integer',
                Rule::exists('assessment_components', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
                // One score per student per subject per component.
                Rule::unique('results')->where(fn ($q) => $q
                    ->where('enrollment_id', $this->input('enrollment_id'))
                    ->where('subject_id', $this->input('subject_id'))),
            ],

            'score' => [
                'required', 'numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    $component = AssessmentComponent::find($this->input('assessment_component_id'));
                    if ($component && $value > $component->max_score) {
                        $fail("The score must not exceed the component's max score ({$component->max_score}).");
                    }
                },
            ],
        ];
    }
}
