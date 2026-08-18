<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncSubjectClassLevelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('subject'));
    }

    public function rules(): array
    {
        /** @var Subject $subject */
        $subject = $this->route('subject');

        return [
            'class_level_ids'   => ['present', 'array'],
            'class_level_ids.*' => [
                'integer',
                Rule::exists('class_levels', 'id')->where(fn ($q) => $q->where('school_id', $subject->school_id)),
            ],
        ];
    }
}
