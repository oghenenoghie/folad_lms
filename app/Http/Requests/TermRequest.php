<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by TermPolicy via authorizeResource
    }

    public function rules(): array
    {
        $termId = $this->route('term')?->id;

        return [
            'academic_session_id' => [
                'required',
                Rule::exists('academic_sessions', 'id')->where('school_id', $this->user()->school_id),
            ],
            'name'       => ['required', 'string', 'max:20'],
            'sequence'   => [
                'required', 'integer', 'min:1', 'max:10',
                Rule::unique('terms', 'sequence')
                    ->where('academic_session_id', $this->input('academic_session_id'))
                    ->ignore($termId),
            ],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
