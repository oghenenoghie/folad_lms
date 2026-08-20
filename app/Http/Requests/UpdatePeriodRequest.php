<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('period'));
    }

    public function rules(): array
    {
        $period = $this->route('period');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],

            'sequence' => [
                'sometimes', 'required', 'integer', 'min:1',
                Rule::unique('periods')->where(fn ($q) => $q->where('school_id', $period->school_id))->ignore($period->id),
            ],

            'is_teaching_period' => ['sometimes', 'boolean'],
        ];
    }
}
