<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('class_level'));
    }

    public function rules(): array
    {
        $classLevel = $this->route('class_level');
        $schoolId = Tenancy::schoolId() ?? $classLevel->school_id;

        return [
            // school_id is deliberately not accepted here — moving a class level
            // between schools is a tenant-transfer operation, not a field edit.
            'name' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('class_levels')->where(fn ($q) => $q->where('school_id', $schoolId))->ignore($classLevel->id),
            ],
            'rank' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
