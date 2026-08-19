<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', School::class);
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:150'],
            'code'     => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('schools')],
            'email'    => ['nullable', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'address'  => ['nullable', 'string'],
            'motto'    => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
