<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('result'));
    }

    public function rules(): array
    {
        $result = $this->route('result');

        return [
            // enrollment_id/subject_id/assessment_component_id are deliberately
            // not accepted here -- together they're this row's identity (see
            // the unique constraint). Fixing a score entered against the wrong
            // subject/component is "delete and re-create", not a field edit.
            'enrollment_id'           => ['prohibited'],
            'subject_id'              => ['prohibited'],
            'assessment_component_id' => ['prohibited'],

            'score' => [
                'sometimes', 'required', 'numeric', 'min:0',
                function ($attribute, $value, $fail) use ($result) {
                    $maxScore = $result->assessmentComponent?->max_score;
                    if ($maxScore !== null && $value > $maxScore) {
                        $fail("The score must not exceed the component's max score ({$maxScore}).");
                    }
                },
            ],
        ];
    }
}
