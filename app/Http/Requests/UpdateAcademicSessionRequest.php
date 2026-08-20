<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('academic_session'));
    }

    public function rules(): array
    {
        $session = $this->route('academic_session');
        $schoolId = Tenancy::schoolId() ?? $session->school_id;
        $startDate = $this->input('start_date', optional($session->start_date)->toDateString());

        return [
            // school_id is deliberately not accepted here — moving a session
            // between schools is a tenant-transfer operation, not a field edit.
            'name' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('academic_sessions')->where(fn ($q) => $q->where('school_id', $schoolId))->ignore($session->id),
            ],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date'   => ['sometimes', 'required', 'date', 'after:'.$startDate],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
