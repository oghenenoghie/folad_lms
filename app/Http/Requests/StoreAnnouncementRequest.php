<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use App\Support\Tenancy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Announcement::class);
    }

    public function rules(): array
    {
        $schoolId = Tenancy::schoolId();
        $effectiveSchoolId = $schoolId ?? $this->input('school_id');

        return [
            'school_id' => $schoolId
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id'],

            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'audience' => ['required', Rule::in(['all', 'staff', 'students', 'guardians'])],

            'class_level_id' => [
                'nullable', 'integer',
                Rule::exists('class_levels', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],
            'class_arm_id' => [
                'nullable', 'integer',
                Rule::exists('class_arms', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            // Omit to save as a draft; a past-or-now timestamp publishes
            // immediately; a future one schedules it.
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('class_level_id') && $this->filled('class_arm_id')) {
                $validator->errors()->add('class_arm_id', 'Set only one of class_level_id or class_arm_id, not both -- a class arm already implies its level.');
            }

            if ($this->filled('published_at') && $this->filled('expires_at')
                && $this->date('expires_at')->lessThanOrEqualTo($this->date('published_at'))) {
                $validator->errors()->add('expires_at', 'The expiry must be after the publish date.');
            }
        });
    }
}
