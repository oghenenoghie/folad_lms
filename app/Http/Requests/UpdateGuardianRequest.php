<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('guardian'));
    }

    public function rules(): array
    {
        $guardian = $this->route('guardian');
        $schoolId = Tenancy::schoolId() ?? $guardian->school_id;

        // Which login account a guardian record is linked to is an admin decision —
        // letting a guardian repoint their own record's user_id would let them
        // reassign who controls it.
        $canLinkAccount = $this->user()->isSuperAdmin() || $this->user()->hasRole('school_admin');

        return [
            // school_id is deliberately not accepted here — moving a guardian between
            // schools is a tenant-transfer operation, not a field edit.
            'user_id' => $canLinkAccount
                ? ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('school_id', $schoolId))]
                : ['prohibited'],

            'first_name'  => ['sometimes', 'required', 'string', 'max:100'],
            'last_name'   => ['sometimes', 'required', 'string', 'max:100'],
            'phone'       => ['sometimes', 'required', 'string', 'max:30'],
            'email'       => ['nullable', 'email', 'max:255'],
            'occupation'  => ['nullable', 'string', 'max:150'],
            'address'     => ['nullable', 'string'],
        ];
    }
}
