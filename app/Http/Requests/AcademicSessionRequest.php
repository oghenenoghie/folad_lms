<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by AcademicSessionPolicy via authorizeResource
    }

    public function rules(): array
    {
        $sessionId = $this->route('academic_session')?->id;

        return [
            'name'       => [
                'required', 'string', 'max:20',
                Rule::unique('academic_sessions', 'name')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($sessionId),
            ],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
