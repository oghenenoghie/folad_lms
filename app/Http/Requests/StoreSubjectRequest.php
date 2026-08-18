<?php

namespace App\Http\Requests;

use App\Models\Subject;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Subject::class);
    }

    public function rules(): array
    {
        // Tenant staff never choose the school — it's implied by their own account.
        // Only super_admin (no school of their own) must name one explicitly.
        $schoolId = Tenancy::schoolId();

        return [
            'school_id' => $schoolId
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id'],

            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subjects')->where(fn ($q) => $q->where('school_id', $schoolId ?? $this->input('school_id'))),
            ],
            'code'    => ['nullable', 'string', 'max:20'],
            'is_core' => ['sometimes', 'boolean'],
        ];
    }
}
