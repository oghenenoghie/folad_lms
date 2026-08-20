<?php

namespace App\Http\Requests;

use App\Models\AssessmentComponent;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AssessmentComponent::class);
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

            'term_id' => [
                'required', 'integer',
                Rule::exists('terms', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('assessment_components')->where(fn ($q) => $q->where('term_id', $this->input('term_id'))),
            ],

            'max_score' => ['required', 'numeric', 'min:1'],
            'sequence'  => ['required', 'integer', 'min:1'],
        ];
    }
}
