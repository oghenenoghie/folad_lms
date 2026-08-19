<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by ClassLevelPolicy via authorizeResource
    }

    public function rules(): array
    {
        $classLevelId = $this->route('class_level')?->id;

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('class_levels', 'name')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($classLevelId),
            ],
            'rank' => ['required', 'integer', 'min:1'],
        ];
    }
}
