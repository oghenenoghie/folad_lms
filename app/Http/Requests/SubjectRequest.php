<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by SubjectPolicy via authorizeResource
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject')?->id;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subjects', 'name')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($subjectId),
            ],
            'code'    => ['nullable', 'string', 'max:20'],
            'is_core' => ['sometimes', 'boolean'],
        ];
    }
}
