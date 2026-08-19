<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated per-controller (only school_admin/super_admin reach these actions)
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8'],
            'role'     => ['required', 'string', Rule::in($this->allowedRoles())],
        ];
    }

    protected function allowedRoles(): array
    {
        return [
            'teacher', 'head_teacher', 'accountant', 'bursar', 'school_admin', 'student', 'guardian',
        ];
    }
}
