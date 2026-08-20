<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('term'));
    }

    public function rules(): array
    {
        $term = $this->route('term');
        $schoolId = Tenancy::schoolId() ?? $term->school_id;
        $startDate = $this->input('start_date', optional($term->start_date)->toDateString());

        return [
            // school_id and academic_session_id are deliberately not accepted
            // here — a term belongs to exactly one session for its whole life;
            // re-pointing it is "delete and re-create", not a field edit.
            'academic_session_id' => ['prohibited'],

            'name' => ['sometimes', 'required', 'string', 'max:50'],

            'sequence' => [
                'sometimes', 'required', 'integer', 'min:1', 'max:255',
                Rule::unique('terms')->where(fn ($q) => $q->where('academic_session_id', $term->academic_session_id))->ignore($term->id),
            ],

            'start_date' => ['sometimes', 'required', 'date'],
            'end_date'   => ['sometimes', 'required', 'date', 'after:'.$startDate],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
