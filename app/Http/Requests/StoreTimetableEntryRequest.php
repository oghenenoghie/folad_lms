<?php

namespace App\Http\Requests;

use App\Models\ClassArm;
use App\Models\Period;
use App\Models\TimetableEntry;
use App\Support\Tenancy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TimetableEntry::class);
    }

    public function rules(): array
    {
        $schoolId = Tenancy::schoolId();
        $effectiveSchoolId = $schoolId ?? $this->input('school_id');

        return [
            'school_id' => $schoolId
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id'],

            'class_arm_id' => [
                'required', 'integer',
                Rule::exists('class_arms', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'term_id' => [
                'required', 'integer',
                Rule::exists('terms', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'day_of_week' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],

            'period_id' => [
                'required', 'integer',
                Rule::exists('periods', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
                // A class arm can't be in two places in the same period.
                Rule::unique('timetable_entries')->where(fn ($q) => $q
                    ->where('class_arm_id', $this->input('class_arm_id'))
                    ->where('term_id', $this->input('term_id'))
                    ->where('day_of_week', $this->input('day_of_week'))),
            ],

            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'staff_id' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
                // A teacher can't be in two places in the same period.
                Rule::unique('timetable_entries')->where(fn ($q) => $q
                    ->where('school_id', $effectiveSchoolId)
                    ->where('term_id', $this->input('term_id'))
                    ->where('day_of_week', $this->input('day_of_week'))
                    ->where('period_id', $this->input('period_id'))),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $periodId = $this->input('period_id');
            $period = $periodId ? Period::find($periodId) : null;

            if ($period && ! $period->is_teaching_period) {
                $validator->errors()->add('period_id', 'This period is not a teaching period.');
            }

            $classArmId = $this->input('class_arm_id');
            $subjectId = $this->input('subject_id');
            $classArm = $classArmId ? ClassArm::find($classArmId) : null;

            if ($classArm && $subjectId && ! $classArm->classLevel?->subjects()->whereKey($subjectId)->exists()) {
                $validator->errors()->add('subject_id', 'This subject is not taught at this class arm\'s level.');
            }
        });
    }
}
