<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by SchoolPolicy via authorizeResource
    }

    public function rules(): array
    {
        $schoolId = $this->route('school')?->id;
        $isCreate = $this->isMethod('post');

        return [
            'name'     => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'code'     => [$isCreate ? 'required' : 'sometimes', 'string', 'max:50', Rule::unique('schools', 'code')->ignore($schoolId)],
            'email'    => ['nullable', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'address'  => ['nullable', 'string'],
            'motto'    => ['nullable', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
