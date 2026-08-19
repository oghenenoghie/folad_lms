<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by StaffPolicy via authorizeResource
    }

    public function rules(): array
    {
        $staffId = $this->route('staff')?->id;

        return [
            'staff_number' => [
                'required', 'string', 'max:30',
                Rule::unique('staff', 'staff_number')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($staffId),
            ],
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'middle_name'     => ['nullable', 'string', 'max:100'],
            'gender'          => ['nullable', Rule::in(['male', 'female'])],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'designation'     => ['nullable', 'string', 'max:100'],
            'department'      => ['nullable', 'string', 'max:100'],
            'employment_date' => ['nullable', 'date'],
            'status'          => ['sometimes', Rule::in(['active', 'on_leave', 'terminated'])],
        ];
    }
}
