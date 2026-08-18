<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('school'));
    }

    public function rules(): array
    {
        $school = $this->route('school');

        // A school_admin manages their own profile (contact details, branding),
        // but code/currency/is_active are platform-level decisions -- changing
        // the unique slug or deactivating the tenant is super_admin-only.
        $isSuperAdmin = $this->user()->isSuperAdmin();
        $adminOnly = fn (array $rules) => $isSuperAdmin ? $rules : ['prohibited'];

        return [
            'name'       => ['sometimes', 'required', 'string', 'max:150'],
            'code'       => $adminOnly(['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('schools')->ignore($school->id)]),
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'address'    => ['nullable', 'string'],
            'motto'      => ['nullable', 'string', 'max:255'],
            'logo_path'  => ['nullable', 'string', 'max:255'],
            'settings'   => ['nullable', 'array'],
            'currency'   => $adminOnly(['sometimes', 'required', 'string', 'size:3']),
            'is_active'  => $adminOnly(['sometimes', 'required', 'boolean']),
        ];
    }
}
