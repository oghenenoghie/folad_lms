<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassArmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('class_arm'));
    }

    public function rules(): array
    {
        $classArm = $this->route('class_arm');
        $schoolId = Tenancy::schoolId() ?? $classArm->school_id;
        $classLevelId = $this->input('class_level_id', $classArm->class_level_id);

        return [
            // school_id is deliberately not accepted here — moving a class arm
            // between schools is a tenant-transfer operation, not a field edit.
            'class_level_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('class_levels', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],

            'name' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('class_arms')
                    ->where(fn ($q) => $q->where('class_level_id', $classLevelId))
                    ->ignore($classArm->id),
            ],

            'form_teacher_id' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],

            'capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
