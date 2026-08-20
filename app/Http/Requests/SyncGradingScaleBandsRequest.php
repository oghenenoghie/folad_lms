<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SyncGradingScaleBandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('grading_scale'));
    }

    public function rules(): array
    {
        return [
            'bands'              => ['present', 'array'],
            'bands.*.grade'      => ['required', 'string', 'max:20'],
            'bands.*.min_score'  => ['required', 'numeric', 'min:0'],
            'bands.*.max_score'  => ['required', 'numeric', 'gte:bands.*.min_score'],
            'bands.*.remark'     => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Cross-row checks that can't be expressed as a per-field rule: no duplicate grades, no overlapping ranges. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bands = collect($this->input('bands', []))
                ->filter(fn ($band) => isset($band['min_score'], $band['max_score']))
                ->values();

            $grades = $bands->pluck('grade');
            if ($grades->duplicates()->isNotEmpty()) {
                $validator->errors()->add('bands', 'Grade labels must be unique within a scale.');
            }

            $sorted = $bands->sortBy('min_score')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]['min_score'] <= $sorted[$i - 1]['max_score']) {
                    $validator->errors()->add('bands', 'Band score ranges must not overlap.');
                    break;
                }
            }
        });
    }
}
