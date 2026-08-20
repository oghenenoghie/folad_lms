<?php

namespace App\Http\Requests;

use App\Models\AcademicSession;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AcademicSession::class);
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
                'required', 'string', 'max:20',
                Rule::unique('academic_sessions')->where(fn ($q) => $q->where('school_id', $schoolId ?? $this->input('school_id'))),
            ],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
