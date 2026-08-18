<?php

namespace App\Http\Requests;

use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachGuardianStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageStudents', $this->route('guardian'));
    }

    public function rules(): array
    {
        /** @var Guardian $guardian */
        $guardian = $this->route('guardian');

        return [
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where(fn ($q) => $q->where('school_id', $guardian->school_id)),
                Rule::unique('guardian_student')->where(fn ($q) => $q->where('guardian_id', $guardian->id)),
            ],
            'relationship'       => ['required', 'string', 'max:50'],
            'is_primary_contact' => ['sometimes', 'boolean'],
        ];
    }
}
