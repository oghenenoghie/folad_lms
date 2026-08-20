<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Enrollment */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'status' => $this->status,

            'student' => $this->whenLoaded('student', fn () => [
                'id'               => $this->student->id,
                'admission_number' => $this->student->admission_number,
                'full_name'        => $this->student->full_name,
            ]),

            'class_arm' => $this->whenLoaded('classArm', fn () => [
                'id'        => $this->classArm->id,
                'full_name' => $this->classArm->full_name,
            ]),

            'academic_session' => $this->whenLoaded('academicSession', fn () => [
                'id'         => $this->academicSession->id,
                'name'       => $this->academicSession->name,
                'is_current' => $this->academicSession->is_current,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
