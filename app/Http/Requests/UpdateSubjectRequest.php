<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('subject'));
    }

    public function rules(): array
    {
        $subject = $this->route('subject');
        $schoolId = Tenancy::schoolId() ?? $subject->school_id;

        return [
            // school_id is deliberately not accepted here — moving a subject
            // between schools is a tenant-transfer operation, not a field edit.
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('subjects')->where(fn ($q) => $q->where('school_id', $schoolId))->ignore($subject->id),
            ],
            'code'    => ['nullable', 'string', 'max:20'],
            'is_core' => ['sometimes', 'boolean'],
        ];
    }
}
