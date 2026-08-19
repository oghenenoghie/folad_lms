<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'school_id'            => $this->school_id,
            'student_id'           => $this->student_id,
            'class_arm_id'         => $this->class_arm_id,
            'academic_session_id'  => $this->academic_session_id,
            'status'               => $this->status,
            'student'              => new StudentResource($this->whenLoaded('student')),
            'class_arm'            => new ClassArmResource($this->whenLoaded('classArm')),
            'academic_session'     => new AcademicSessionResource($this->whenLoaded('academicSession')),
        ];
    }
}
