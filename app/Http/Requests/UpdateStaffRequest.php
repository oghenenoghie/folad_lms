<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('staff'));
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $schoolId = Tenancy::schoolId() ?? $staff->school_id;
        $isAdmin = $this->user()->isSuperAdmin() || $this->user()->hasRole('school_admin');

        // A staff member updating their own record can only touch contact
        // details. HR fields (name, number, designation, status, employment
        // date, which login it's linked to) are admin-only.
        $adminOnly = fn (array $rules) => $isAdmin ? $rules : ['prohibited'];

        return [
            // school_id is deliberately not accepted here — moving a staff member
            // between schools is a tenant-transfer operation, not a field edit.
            'user_id' => $adminOnly([
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ]),

            'staff_number' => $adminOnly([
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('staff')->where(fn ($q) => $q->where('school_id', $schoolId))->ignore($staff->id),
            ]),

            'first_name'      => $adminOnly(['sometimes', 'required', 'string', 'max:100']),
            'last_name'       => $adminOnly(['sometimes', 'required', 'string', 'max:100']),
            'middle_name'     => $adminOnly(['nullable', 'string', 'max:100']),
            'gender'          => $adminOnly(['nullable', Rule::in(['male', 'female'])]),
            'designation'     => $adminOnly(['nullable', 'string', 'max:150']),
            'department'      => $adminOnly(['nullable', 'string', 'max:150']),
            'employment_date' => $adminOnly(['nullable', 'date']),
            'status'          => $adminOnly(['nullable', Rule::in(['active', 'on_leave', 'terminated'])]),

            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
