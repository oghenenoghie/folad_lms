<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncGuardianStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated in GuardianController@syncStudents
    }

    public function rules(): array
    {
        return [
            'students'                      => ['required', 'array'],
            'students.*.student_id'         => ['required', 'integer', 'exists:students,id'],
            'students.*.relationship'       => ['required', 'string', 'max:50'],
            'students.*.is_primary_contact' => ['sometimes', 'boolean'],
        ];
    }
}
