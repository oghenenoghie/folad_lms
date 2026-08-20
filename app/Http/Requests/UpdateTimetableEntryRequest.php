<?php

namespace App\Http\Requests;

use App\Models\ClassArm;
use App\Models\Period;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('timetable_entry'));
    }

    public function rules(): array
    {
        $entry = $this->route('timetable_entry');

        // A partial update still has to check the *resulting* row against the
        // two scheduling constraints, so fall back to the existing values for
        // whichever fields this request doesn't touch.
        $dayOfWeek = $this->input('day_of_week', $entry->day_of_week);
        $periodId = $this->input('period_id', $entry->period_id);
        $staffId = $this->has('staff_id') ? $this->input('staff_id') : $entry->staff_id;

        return [
            // class_arm_id and term_id are this row's identity — moving an
            // entry to a different class arm or term is delete-and-recreate,
            // not a field edit.
            'class_arm_id' => ['prohibited'],
            'term_id' => ['prohibited'],

            'day_of_week' => ['sometimes', 'required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],

            'period_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('periods', 'id')->where(fn ($q) => $q->where('school_id', $entry->school_id)),
                Rule::unique('timetable_entries')->where(fn ($q) => $q
                    ->where('class_arm_id', $entry->class_arm_id)
                    ->where('term_id', $entry->term_id)
                    ->where('day_of_week', $dayOfWeek))->ignore($entry->id),
            ],

            'subject_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('school_id', $entry->school_id)),
            ],

            'staff_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('school_id', $entry->school_id)),
                Rule::unique('timetable_entries')->where(fn ($q) => $q
                    ->where('school_id', $entry->school_id)
                    ->where('term_id', $entry->term_id)
                    ->where('day_of_week', $dayOfWeek)
                    ->where('period_id', $periodId))->ignore($entry->id),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $entry = $this->route('timetable_entry');

            if ($this->has('period_id')) {
                $period = Period::find($this->input('period_id'));

                if ($period && ! $period->is_teaching_period) {
                    $validator->errors()->add('period_id', 'This period is not a teaching period.');
                }
            }

            if ($this->has('subject_id')) {
                $classArm = ClassArm::find($entry->class_arm_id);

                if ($classArm && ! $classArm->classLevel?->subjects()->whereKey($this->input('subject_id'))->exists()) {
                    $validator->errors()->add('subject_id', 'This subject is not taught at this class arm\'s level.');
                }
            }
        });
    }
}
