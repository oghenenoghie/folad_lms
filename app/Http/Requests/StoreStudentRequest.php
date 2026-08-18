<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Student::class);
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

            'admission_number' => [
                'required', 'string', 'max:50',
                Rule::unique('students')->where(fn ($q) => $q->where('school_id', $schoolId ?? $this->input('school_id'))),
            ],

            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'middle_name'     => ['nullable', 'string', 'max:100'],
            'gender'          => ['required', Rule::in(['male', 'female'])],
            'date_of_birth'   => ['required', 'date', 'before:today'],
            'admission_date'  => ['nullable', 'date'],
            'blood_group'     => ['nullable', 'string', 'max:10'],
            'address'         => ['nullable', 'string'],
            'status'          => ['nullable', Rule::in(['enrolled', 'graduated', 'withdrawn', 'suspended'])],
        ];
    }
}
