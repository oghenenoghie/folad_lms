<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradingScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('grading_scale'));
    }

    public function rules(): array
    {
        $scale = $this->route('grading_scale');
        $schoolId = Tenancy::schoolId() ?? $scale->school_id;

        return [
            // school_id is deliberately not accepted here — moving a grading
            // scale between schools is a tenant-transfer operation, not a
            // field edit.
            'class_level_id' => [
                'nullable', 'integer',
                Rule::exists('class_levels', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],

            'name'       => ['sometimes', 'required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
