<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('assessment_component'));
    }

    public function rules(): array
    {
        $component = $this->route('assessment_component');

        return [
            // school_id and term_id are deliberately not accepted here — a
            // component belongs to exactly one term; re-pointing it is
            // "delete and re-create", not a field edit.
            'term_id' => ['prohibited'],

            'name' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('assessment_components')
                    ->where(fn ($q) => $q->where('term_id', $component->term_id))
                    ->ignore($component->id),
            ],

            'max_score' => ['sometimes', 'required', 'numeric', 'min:1'],
            'sequence'  => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
