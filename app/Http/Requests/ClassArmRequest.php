<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassArmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by ClassArmPolicy via authorizeResource
    }

    public function rules(): array
    {
        $classArmId = $this->route('class_arm')?->id;

        return [
            'class_level_id' => [
                'required',
                Rule::exists('class_levels', 'id')->where('school_id', $this->user()->school_id),
            ],
            'form_teacher_id' => [
                'nullable',
                Rule::exists('staff', 'id')->where('school_id', $this->user()->school_id),
            ],
            'name' => [
                'required', 'string', 'max:30',
                Rule::unique('class_arms', 'name')
                    ->where('class_level_id', $this->input('class_level_id'))
                    ->ignore($classArmId),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
