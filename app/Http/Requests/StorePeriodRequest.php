<?php

namespace App\Http\Requests;

use App\Models\Period;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Period::class);
    }

    public function rules(): array
    {
        $schoolId = Tenancy::schoolId();

        return [
            'school_id' => $schoolId
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id'],

            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],

            'sequence' => [
                'required', 'integer', 'min:1',
                Rule::unique('periods')->where(fn ($q) => $q->where('school_id', $schoolId ?? $this->input('school_id'))),
            ],

            'is_teaching_period' => ['sometimes', 'boolean'],
        ];
    }
}
