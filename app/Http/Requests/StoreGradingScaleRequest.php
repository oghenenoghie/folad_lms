<?php

namespace App\Http\Requests;

use App\Models\GradingScale;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradingScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GradingScale::class);
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

            'class_level_id' => [
                'nullable', 'integer',
                Rule::exists('class_levels', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'name'       => ['required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
