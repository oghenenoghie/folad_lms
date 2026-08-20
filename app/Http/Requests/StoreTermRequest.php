<?php

namespace App\Http\Requests;

use App\Models\Term;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Term::class);
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

            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'name' => ['required', 'string', 'max:50'],

            'sequence' => [
                'required', 'integer', 'min:1', 'max:255',
                Rule::unique('terms')->where(fn ($q) => $q->where('academic_session_id', $this->input('academic_session_id'))),
            ],

            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
