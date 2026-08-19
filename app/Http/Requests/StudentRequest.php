<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by StudentPolicy via authorizeResource
    }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id;

        return [
            'admission_number' => [
                'required', 'string', 'max:30',
                Rule::unique('students', 'admission_number')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($studentId),
            ],
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'middle_name'     => ['nullable', 'string', 'max:100'],
            'gender'          => ['required', Rule::in(['male', 'female'])],
            'date_of_birth'   => ['required', 'date', 'before:today'],
            'admission_date'  => ['nullable', 'date'],
            'blood_group'     => ['nullable', 'string', 'max:10'],
            'address'         => ['nullable', 'string'],
            'status'          => ['sometimes', Rule::in(['enrolled', 'graduated', 'withdrawn', 'suspended'])],
        ];
    }
}
