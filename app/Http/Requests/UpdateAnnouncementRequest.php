<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('announcement'));
    }

    public function rules(): array
    {
        $announcement = $this->route('announcement');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'body' => ['sometimes', 'required', 'string'],
            'audience' => ['sometimes', 'required', Rule::in(['all', 'staff', 'students', 'guardians'])],

            'class_level_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('class_levels', 'id')->where(fn ($q) => $q->where('school_id', $announcement->school_id)),
            ],
            'class_arm_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('class_arms', 'id')->where(fn ($q) => $q->where('school_id', $announcement->school_id)),
            ],

            'published_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $announcement = $this->route('announcement');

            $classLevelId = $this->has('class_level_id') ? $this->input('class_level_id') : $announcement->class_level_id;
            $classArmId = $this->has('class_arm_id') ? $this->input('class_arm_id') : $announcement->class_arm_id;

            if ($classLevelId && $classArmId) {
                $validator->errors()->add('class_arm_id', 'Set only one of class_level_id or class_arm_id, not both -- a class arm already implies its level.');
            }

            $publishedAt = $this->has('published_at') ? $this->date('published_at') : $announcement->published_at;
            $expiresAt = $this->has('expires_at') ? $this->date('expires_at') : $announcement->expires_at;

            if ($publishedAt && $expiresAt && $expiresAt->lessThanOrEqualTo($publishedAt)) {
                $validator->errors()->add('expires_at', 'The expiry must be after the publish date.');
            }
        });
    }
}
