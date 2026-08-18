<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    public function rules(): array
    {
        $student = $this->route('student');
        $schoolId = Tenancy::schoolId() ?? $student->school_id;

        return [
            // school_id is deliberately not accepted here — moving a student between
            // schools is a tenant-transfer operation, not a field edit.
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],

            'admission_number' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('students')
                    ->where(fn ($q) => $q->where('school_id', $schoolId))
                    ->ignore($student->id),
            ],

            'first_name'      => ['sometimes', 'required', 'string', 'max:100'],
            'last_name'       => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name'     => ['nullable', 'string', 'max:100'],
            'gender'          => ['sometimes', 'required', Rule::in(['male', 'female'])],
            'date_of_birth'   => ['sometimes', 'required', 'date', 'before:today'],
            'admission_date'  => ['nullable', 'date'],
            'blood_group'     => ['nullable', 'string', 'max:10'],
            'address'         => ['nullable', 'string'],
            'status'          => ['sometimes', Rule::in(['enrolled', 'graduated', 'withdrawn', 'suspended'])],
        ];
    }
}
