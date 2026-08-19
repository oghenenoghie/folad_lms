<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('attendance'));
    }

    public function rules(): array
    {
        return [
            // enrollment_id and date are deliberately not accepted here —
            // together they're this row's identity (see the unique
            // constraint). A record for the wrong day is delete-and-recreate,
            // not a field edit.
            'enrollment_id' => ['prohibited'],
            'date'          => ['prohibited'],

            'status'  => ['sometimes', 'required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
