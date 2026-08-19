<?php

namespace App\Http\Requests;

use App\Models\Guardian;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Guardian::class);
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

            'user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('school_id', $schoolId ?? $this->input('school_id'))),
            ],

            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'phone'       => ['required', 'string', 'max:30'],
            'email'       => ['nullable', 'email', 'max:255'],
            'occupation'  => ['nullable', 'string', 'max:150'],
            'address'     => ['nullable', 'string'],
        ];
    }
}
