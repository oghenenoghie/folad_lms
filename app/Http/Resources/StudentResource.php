<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Student */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'school_id'        => $this->school_id,
            'admission_number' => $this->admission_number,
            'first_name'       => $this->first_name,
            'middle_name'      => $this->middle_name,
            'last_name'        => $this->last_name,
            'full_name'        => $this->full_name,
            'gender'           => $this->gender,
            'date_of_birth'    => $this->date_of_birth?->toDateString(),
            'admission_date'   => $this->admission_date?->toDateString(),
            'blood_group'      => $this->blood_group,
            'address'          => $this->address,
            'status'           => $this->status,

            // Derived, never stored: the student's placement in the current session.
            'current_class' => $this->whenLoaded('currentEnrollment', fn () => $this->currentEnrollment ? [
                'enrollment_id'       => $this->currentEnrollment->id,
                'class_arm_id'        => $this->currentEnrollment->classArm?->id,
                'class_arm'           => $this->currentEnrollment->classArm?->full_name,
                'academic_session_id' => $this->currentEnrollment->academic_session_id,
            ] : null),

            'guardians' => GuardianSummaryResource::collection($this->whenLoaded('guardians')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
