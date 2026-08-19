<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassArmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_id'       => $this->school_id,
            'class_level_id'  => $this->class_level_id,
            'form_teacher_id' => $this->form_teacher_id,
            'name'            => $this->name,
            'full_name'       => $this->full_name,
            'capacity'        => $this->capacity,
            'class_level'     => new ClassLevelResource($this->whenLoaded('classLevel')),
            'form_teacher'    => new StaffResource($this->whenLoaded('formTeacher')),
            'enrolled_count'  => $this->whenCounted('enrollments'),
        ];
    }
}
